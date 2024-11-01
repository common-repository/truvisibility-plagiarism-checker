function getLastCheckedTime(isoDateTime) {

    var lng = navigator.browserLanguage || navigator.language || navigator.userLanguage;

    var twoDay = 172800000;
    var oneDay = 86400000;
    var oneHour = 3600000;
    var oneMinute = 60000;

    var lastCheckDate = new Date(isoDateTime);
    var nowDate = new Date();

    switch (true) {
    case nowDate - lastCheckDate > twoDay:
        return lastCheckDate.toLocaleString(lng,
            {
                year: "numeric",
                month: "long",
                day: "numeric",
                hour: "numeric",
                minute: "numeric"
            });

    case nowDate - lastCheckDate > oneDay:
        return "yesterday " + lastCheckDate.toLocaleString(lng,
            {
                hour: "numeric",
                minute: "numeric"
            });
    case nowDate - lastCheckDate > oneHour * 5:
        return "today  " + lastCheckDate.toLocaleString(lng,
            {
                hour: "numeric",
                minute: "numeric"
            });
    case nowDate - lastCheckDate > oneHour:
        return new Date(nowDate - lastCheckDate).getUTCHours() + " hours ago";
    case nowDate - lastCheckDate > oneMinute:
        return new Date(nowDate - lastCheckDate).getUTCMinutes() + " minutes ago";
    case nowDate - lastCheckDate < oneMinute:
        return "less than a minute ago";
    default:
        return "";
    }
}

function getDateUtc() {
    var now = new Date();
    return new Date(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()));
}