$(document).ready(function () {
    createLicenseDecisionTable();
});

function removeLicense(uploadId, uploadTreeId, licenseId) {
    $.getJSON("?mod=view-license&ajaxMethod=removeLicense&upload=" + uploadId + "&item=" + uploadTreeId + "&licenseId=" + licenseId + "&global=" + $('[name="global_license_decision"]:checked').val())
        .done(function (data) {
            var table = createLicenseDecisionTable();
            table.fnDraw(false);
        })
        .fail(
        failed
    );
    //.fail(failed());
}