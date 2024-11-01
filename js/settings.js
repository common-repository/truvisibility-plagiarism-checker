jQuery(document).ready(function ($) {

    var restUrl = rastSettings.restUrl + "truvisibility/plagiarism/v1/";
    var umbrellaRoot = rastSettings.umbrellaRoot;

    $("#auth-button").click(function () {
        var authorizationUrl =
            "https://auth." + umbrellaRoot + "/oauth/authorize" +
                "?client_id=6202f4a6-c009-4ff8-8cec-60b120119577" +
                "&redirect_uri=https://auth." + umbrellaRoot + "/oauth/authorization-code" +
                "&response_type=code" +
                "&scope=plagiarism.check,plagiarism.buyextra";

        window.open(authorizationUrl, "TruVisibility Authorization", "width=800,height=600");

        $("#auth-button").hide();
        $("#auth-code").show();
        $("#auth-code-apply").click(function () {
            $.get(restUrl + "auth/" + $("#auth-code-value").val()).done(function() {
                $.post(restUrl + "account/ensure").done(function() {
                    window.location.reload();
                });
            });
        });
    });

    if (!rastSettings.pluginAuthorized) {
        return;
    }

    $("#clean-auth-button").click(function() {
        $.post(restUrl + "auth/clean")
            .done(function (data, statusText, xhr) {
                if (xhr.status === 200) {
                    window.location.reload();
                }
            });
    });

    $.get(restUrl + "plans").done(function (plans) {
        $("#rast-plans-loading").remove();
        
        var header = $("<tr>").css("font-weight", "600")
            .append($("<td>").text("Name").addClass("rast-row"))
            .append($("<td>").text("Checks included").addClass("rast-row"))
            .append($("<td>").text("Price").addClass("rast-row"))
            .append($("<td>").text("Cost of additional check").addClass("rast-row"));

        var table = $("#rast-plans").append(header);

        var isFreePlanActive = plans.some(function(p) { return p.code.startsWith("free") && p.isActive; });

        plans = plans
            .filter(function(p) { return p.code !== "free-12" && p.code !== "free-24" && p.code !== "free-36"; })
            .sort(function(a, b) { return a.price - b.price; });
        
        for (var j = 0; j < plans.length; j++) {
            var plan = plans[j];
            
            if (plan.code === "free-1") plan.isActive = isFreePlanActive;
            if (plan.code.endsWith("-1")) plan.name += " (monthly)";
            
            var subscriptionUpgradeLink = "https://sites." + umbrellaRoot + "/client/purchase#plagiarism-packages:" + plan.code.replace("-", ":");
            
            var row = $("<tr>").css("font-weight", plan.isActive ? "600" : "normal");
            row.append($("<td>").text(plan.name).addClass("rast-row"));
            row.append($("<td>").text(plan.checksPerMonth + " per month").addClass("rast-row"));
            row.append($("<td>").text("$" + plan.price.toFixed(2)).addClass("rast-row"));
            row.append($("<td>").text("$" + getExtraCheckPrice(plan.code).toFixed(2)).addClass("rast-row"));
            row.append($("<td>").html(plan.isActive ? 'current' : '<a href="' + subscriptionUpgradeLink + '" target="_blank">select</a>').addClass("rast-row"));
            table.append(row);
        }

        function getExtraCheckPrice(planCode) {
            if (planCode.startsWith("basic")) return 1.0;
            if (planCode.startsWith("pro")) return 0.9;
            return 1.3;
        }
    });

    $.get(restUrl + "checks/quota/extended").done(function (data) {
        $("#rast-quota-loading").hide();
        $("#rast-quota-view").show();

        if (data.extraChecksPurchasing === 0) $("#rast-purchasing-ok").show();
        if (data.extraChecksPurchasing === 1) $("#rast-purchasing-no-card").show();

        $("#rast-quota-used").text(data.used);
        $("#rast-quota-limit").text(data.limit);

        if (data.invoiceInProgress != null) {
            $("#rast-purchase-info").text("One purchase of " + data.invoiceInProgress.quantity + " checks in progress");
        }
    });

    $("#rast-upgrade").click(function () {
        $("#rast-buy-checks-dialog").show();
        $.get(restUrl + "checks/price")
            .done(function(data) {
                $("#rast-checks-price").text("$" + data.price);
                $("#rast-checks-quantity").text(data.quantity);
                $("#rast-checks-price-loader").hide();
                $("#rast-checks-price-view").show();
            });
    });

    $("#rast-buy-checks").click(function() {
        $.post(restUrl + "checks/buy")
            .done(function (data, statusText, xhr) {
                if (xhr.status === 200) {
                    $("#rast-checks-price-view").hide();
                    $("#rast-purchase-waiter").show();

                    var timerId = setInterval(function() {
                        $.get(restUrl + "checks/quota/extended").done(function (data) {
                            if (data.invoiceInProgress == null) {
                                clearInterval(timerId);
                                window.location.reload();
                            }
                        });
                    }, 1000);
                } else {
                    $("#rast-buy-checks-dialog").hide();
                    $("#rast-purchasing-info").html("<i>Something wrong happened, but we already know about it</i>");
                }
            });
    });

    $("#rast-buy-checks-dialog-close, #rast-buy-checks-cancel").click(function () { $("#rast-buy-checks-dialog").hide(); });
});