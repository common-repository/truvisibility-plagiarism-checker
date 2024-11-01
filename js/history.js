jQuery(document).ready(function ($) {
    
    var url = rastSettings.restUrl + "truvisibility/plagiarism/v1/history";
    var historyUpdatingTimeout = 10000;
    var currentInterval = getInterval(window.location.hash);

    $("#rast-interval-" + currentInterval).addClass("active");

    updateHistoryList();
    var interval = setInterval(updateHistoryList, historyUpdatingTimeout);

    $(window).on("hashchange", function () {
        clearInterval(interval);

        $("#rast-interval-" + currentInterval).removeClass("active");
        currentInterval = getInterval(window.location.hash);
        $("#rast-interval-" + currentInterval).addClass("active");
        
        updateHistoryList();
        interval = setInterval(updateHistoryList, historyUpdatingTimeout);
    });
    
    function updateHistoryList() {
        var fromTime = getDateUtc();
        
        switch (currentInterval) {
            case "day": fromTime.setDate(fromTime.getDate() - 1); break;
            case "week": fromTime.setDate(fromTime.getDate() - 7); break;
            case "month": fromTime.setMonth(fromTime.getMonth() - 1); break;
        }

        var historyUrl = url + "?from_time=" + fromTime.toISOString() + "&to_time=" + new Date().toISOString();

        $.get(historyUrl).done(function (posts) {
            if (posts == undefined || posts.length === 0) {
                $("#rast-history-label-empty").show();
                $("#rast-history-list").hide();
                return;
            }

            $("#rast-history-label-empty").hide();
            
            var header = $("<tr>")
                .append($("<td>").addClass("manage-column column-number").html("<b>#</b>"))
                .append($("<td>").addClass("manage-column").html("<b>Title</b>"))
                .append($("<td>").addClass("manage-column column-time").html("<b>Time</b>"))
                .append($("<td>").addClass("manage-column column-uniqueness").html("<b>Uniqueness</b>"))
                .append($("<td>").addClass("manage-column column-state").html("<b>State</b>"));

            var body = $("#rast-history-list").empty().show().append(header);

            for (var i = 0; i < posts.length; i++) {
                var postId = posts[i].id.split('/');

                var row = $("<tr>");
                row.append($("<td>").html(i + 1));

                if (postId[0] === "WordPress" && postId[1] === rastSettings.blogId) {
                    var postUrl = rastSettings.adminUrl + "post.php?post=" + postId[2] + "&action=edit&report-time=" + posts[i].time;
                    
                    row.append($("<td>").html("<a href=\"" + postUrl + "\" target=\"_blank\">" + posts[i].title + "</a>"));
                }
                else {
                    row.append($("<td>").html(posts[i].title).attr("title", "This post checked from another blog"));
                }

                row.append($("<td>").html(getLastCheckedTime(posts[i].time)));

                if (posts[i].state === 0)
                    row.append($("<td>").html("—"));
                else
                    row.append($("<td>").html((100 - posts[i].factor).toFixed(2) + "%"));
                
                row.append($("<td>").html(mapState(posts[i].state)));

                body.append(row);
            }
        });
    }
    
    function mapState(state) {
        switch (state) {
            case 0: return "Enqueued";
            case 1: return "In Progress";
            case 2: return "Done";
            case 10: return "Failed with text too short";
            case 11: return "Failed with text too long";
            case 12: return "Failed with search request";
            case 13: return "Failed with loading results";
            case 100: return "Failed otherwise";
            default:
                return null;
        }
    }

    function getInterval(hash) {
        if (hash == undefined || hash.length <= 1)
            return "month";

        var interval = hash.substring(1);

        return (interval === "day" || interval === "week") ? interval : "month";
    }
});