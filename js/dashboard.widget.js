jQuery(document).ready(function($) {

    var accountApiEndpoint = rastSettings.restUrl + "truvisibility/plagiarism/v1/account";
    var historyApiEndpoint = rastSettings.restUrl + "truvisibility/plagiarism/v1/history";

    var historyPageUrl = rastSettings.adminUrl + "admin.php?page=truvisibility_plagiarism_history";

    $.get(accountApiEndpoint).done(function(account) {
        $("#rast-account-loading").hide();
        $("#rast-account-name").text(account.name);
        $("#rast-account-quota").text(account.quota.used + " of " + account.quota.limit + " checks used");
        $("#rast-account").show();
    });
    
    var day = getDateUtc(); day.setDate(day.getDate() - 1);
    var week = getDateUtc(); week.setDate(week.getDate() - 7);
    var month = getDateUtc(); month.setMonth(month.getMonth() - 1);
    
    $.get(historyApiEndpoint + "?from_time=" + month.toISOString() + "&to_time=" + new Date().toISOString()).done(function (checks) {
        var activity = $("#rast-latest-activity").html("");
        
        activity.append('<li style="color: #72777c;">Checks for last</li>');
        activity.append('<li><a href="' + historyPageUrl + '#day">Day <span style="color: #72777c;">(' + countChecksFor(day, checks) + ')</span></a></li>');
        activity.append('<li style="color: #ddd;">|</li>');
        activity.append('<li><a href="' + historyPageUrl + '#week">Week <span style="color: #72777c;">(' + countChecksFor(week, checks) + ')</span></a></li>');
        activity.append('<li style="color: #ddd;">|</li>');
        activity.append('<li><a href="' + historyPageUrl + '#month">Month <span style="color: #72777c;">(' + countChecksFor(month, checks) + ')</span></a></li>');
    });

    function countChecksFor(time, history) {
        var count = 0;

        for (var i = 0; i < history.length; i++)
            if (Date.parse(history[i].time) > time)
                count++;

        return count;
    }
});