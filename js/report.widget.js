jQuery(document).ready(function ($) {

    var postGlobalId = "WordPress/" + rastSettings.currentBlogId + "/" + $("#rast-post-id").text();
    
    var checkUrl = rastSettings.restUrl + "truvisibility/plagiarism/v1/texts/" + postGlobalId;
    var reportUrl = rastSettings.restUrl + "truvisibility/plagiarism/v1/reports/" + postGlobalId;
    var quotaUrl = rastSettings.restUrl + "truvisibility/plagiarism/v1/checks/quota";
    var markPostForPublishingUrl = rastSettings.restUrl + "truvisibility/plagiarism/v1/posts/" + $("#rast-post-id").text() + "/publish";
    
    var publishButton = $("#publish");
    var publishDialog = $("#rast-publish-dialog");
    var warningDialog = $("#rast-warning-dialog");

    $("#rast-publish-dialog-close").click(function () { publishDialog.hide(); });
    $("#rast-warning-dialog-close, #rast-publish-cancel").click(function () { warningDialog.hide(); });

    publishButton.click(function (event, data) {
        if (rastSettings.checkingEnabled && (data == undefined || !data.allowPublish)) {
            event.preventDefault();
            publishDialog.show();

            $.get(quotaUrl).done(function (quota) {
                $("#rast-quota").text(quota.used + " of " + quota.limit + " checks used");

                if (quota.used < quota.limit) {
                    $("#rast-quota-not-exceeded-label").show();
                } else {
                    $("#rast-quota-exceeded-label").show();
                    $("#rast-publish-with-check").addClass("disabled");
                }
            });

            return false;
        }
    });

    $("#rast-publish-with-check").click(function () {
        publishButton.trigger("click", { allowPublish: true });
    });

    $("#rast-publish-without-check, #rast-publish-plagiarized-anyway").click(function () {
        $.post(markPostForPublishingUrl).done(function () {
            publishButton.trigger("click", { allowPublish: true });
        });
    });
    
    if (rastSettings.checkingEnabled) {
        $("#rast-check-again").addClass("button-primary").click(function () {
            var title = $("#titlewrap").find("input#title").val();
            var html = document.getElementById("content_ifr").contentWindow.document.getElementById("tinymce").innerHTML;
            checkPost(title, html);
            showEnqueuedState();
        });
    } else {
        $("#rast-check-again").addClass("button-disabled");
    }
    
    function checkPost(title, html) {
        $.post(checkUrl, { title: title, html: html })
            .done(traceActiveCheck)
            .fail(function (xhr) { showHttpError(xhr.responseJSON); });
    }

    $.get(reportUrl).done(traceActiveCheck);

    function traceActiveCheck(report) {
        renderReport(report, false);

        if (report == null || report.status > 1 || typeof report === 'string') return;

        var timerId = setInterval(function () {
            $.get(reportUrl).done(function (report) {
                if (report == null || report.status > 1) {
                    clearInterval(timerId);
                }

                renderReport(report, true);
            });
        }, 1000);
    }
    
    function showHttpError(textStatus) {
        var errorTag = $("<p/>").attr("class", "rast-intro-par").text(textStatus);
        $("#report-errors").html("").append(errorTag);

        showReportState();
    }

    function showCustomError(state) {
        var message;

        switch (state) {
            case 10: message = "Error! The text is too short."; break;
            case 11: message = "Error! The text is too long."; break;
            default: message = "Oops! Something went wrong.";
        }

        var errorTag = $("<p/>").attr("class", "rast-intro-par").text(message);
        
        $("#report-errors").html("").append(errorTag).show();

        showErrorState();
    }
    
    function renderReport(report, isCheckActive) {
		if (report == null) {
            showNotCheckedState();
            return;
		}

        if (report.status === 0) {
            $("#rast-in-queue").html(
                report.queuePosition === 1
                    ? 'Please wait. <strong>' + report.queuePosition + '</strong> request is ahead of you'
                    : 'Please wait. <strong>' + report.queuePosition + '</strong> requests are ahead of you');

            showEnqueuedState();
            return;
        }

        if (report.status === 1 || report.status === 2) {
            showReportState();
            
            renderPostUniqueness(100 - report.plagiarismFactor, report);
            renderDoughnut(100 - report.plagiarismFactor);

            if (report.status === 1) {
                $("#min-uniqueness-error").hide();
                $("#progress-container").show();
                $("#progress").css("width", report.progress + "%");
                $("#progress-value").text(report.progress.toFixed(0) + "%");
                $("#rast-check-post-again").hide();
            }

            if (report.status === 2) {
                $("#progress-container").hide();
                $("#rast-check-post-again").show();
                $("#rast-report-time").text(getLastCheckedTime(report.time));
                
                if (isCheckActive && report.plagiarismFactor >= report.stopLimit) {
                    warningDialog.show();
                }
            }
            
            report.details =
                (report.details || [])
                    .filter(function (d) { return d.isPlagiarism; })
                    .sort(function(a, b) { return a.start - b.start; });

            if (report.details.length === 0) {
                $("#rast-report-fragments").hide();
                return;
            }
            
            $("#rast-report-fragments").show();
            var textMarkup = $("#tbody").empty();

            report.details = report.details.sort(function (a, b) { return a.start - b.start; });

            for (var i = 0; i < report.details.length; i++) {
                var detail = report.details[i];
                if (detail.isPlagiarism) {
                    var domain = parseDomain(detail.sourceUrl);
                    textMarkup.append(`<tr class="plagiate-detail"><td><p>${detail.sourceFragment}</p><p class='source-url'><a target="_blank" href='${detail.sourceUrl}'><span class="dashicons dashicons-share-alt2"></span>${domain}</a></p></td></tr>`);
                }
            }

            return;
        }

        showCustomError(report.status);
    }

    function showNotCheckedState() {
        $("#rast-errors, #rast-in-queue, #rast-report-info").hide();
        $("#rast-not-checked-early").show();
    }

    function showEnqueuedState() {
        $("#rast-errors, #rast-not-checked-early, #rast-report-info").hide();
        $("#rast-in-queue").show();
    }

    function showReportState() {
        $("#rast-errors, #rast-not-checked-early, #rast-in-queue").hide();
        $("#rast-report-info").show();
    }

    function showErrorState() {
        $("#rast-report-info, #rast-not-checked-early, #rast-in-queue").hide();
        $("#rast-errors").show();
    }

    function renderPostUniqueness(uniqueness, report) {
        var uniquenessLabel = $("#rast-post-uniqueness");
        uniqueness = Math.round(uniqueness);
        if (report.status === 2 && uniqueness < rastSettings.minUniqueness) {
            $("#min-uniqueness-error").css("display", "flex");
            $("#unique-value").text(uniqueness + "% unique");
            $("#min-unique").text((100 - report.stopLimit) + "% unique");
        }

        uniquenessLabel.text(uniqueness + "%");
        var plagiarismLabel = $("#rast-post-plagiarized");
        plagiarismLabel.text(100 - uniqueness + "%");
    }

    function renderDoughnut(uniqueness) {
        uniqueness = Math.round(uniqueness);
        var plagiate = 100 - uniqueness;
        var data = [plagiate, uniqueness];
        var cx = 90;
        var cy = 90;
        var innerRadius = 62;
        var outerRadius = 89;
        var strokeWidth = outerRadius - innerRadius;
        var radius = innerRadius + strokeWidth / 2;
        var perimeter = 2 * Math.PI * radius;

        if (plagiate === 100 || uniqueness === 100) {
            $("#outer-circle").attr({
                cx: cx,
                cy: cy,
                r: outerRadius,
                fill: plagiate === 100 ? "#c55754" : "#1ba694"
            });

            $("#inner-circle").attr({
                cx: cx,
                cy: cy,
                r: innerRadius
            });

            $("#single-value").show();
            $("#multi-value").hide();
            return;
        }

        $("#single-value").hide();
        $("#multi-value").show();
        var x0 = cx + radius;
        var y0 = cy;
        var centralAngle = 0;
        var sectors = [$("#first-sector"), $("#second-sector")];
        for (var i = 0; i < data.length; i++) {
            var arcLength = perimeter * data[i] / 100;
            var currentCenterAngle = arcLength / radius;
            var largeArcFlag = currentCenterAngle >= Math.PI;
            centralAngle += currentCenterAngle;
            var x = cx + radius * Math.cos(centralAngle);
            var y = cy + radius * Math.sin(centralAngle);
            var drawCode = `M${x0},${y0} A${radius}, ${radius} 0 ${largeArcFlag ? 1 : 0}, 1 ${x}, ${y}`;
            x0 = x;
            y0 = y;

            sectors[i].attr('d', drawCode);
            sectors[i].attr('stroke-width', strokeWidth);
        }
    }

    function parseDomain(url) { 
        var re = new RegExp(/((?:(?:(?:\w[\.\-\+]?)*)\w)+)((?:(?:(?:\w[\.\-\+]?){0,62})\w)+)\.(\w{2,6})/); 
        return url.match(re)[0];
    } 
});