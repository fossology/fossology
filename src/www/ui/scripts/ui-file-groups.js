/**
 * SPDX-FileCopyrightText: © 2024 Fossology contributors
 *
 * SPDX-License-Identifier: GPL-2.0-only
 */

/**
 * Logic for File Grouping (Issue #2847)
 */

let currentSuggestions = [];

/**
 * Open the modal and load existing groups
 */
function openFileGroupModal() {
    refreshGroups();
    $('#suggestedGroupsContainer').hide();
    $('#fileGroupModal').modal('show');
}

/**
 * Fetch and display existing groups for the current upload
 */
function refreshGroups() {
    let list = $('#fileGroupsList');
    list.html('<tr><td colspan="5">Loading...</td></tr>');

    $.getJSON(`api/v1/uploads/${uploadId}/filegroups`, function (data) {
        list.empty();
        if (data.length === 0) {
            list.append('<tr><td colspan="5" class="text-center">No groups defined for this upload.</td></tr>');
            return;
        }
        data.forEach(group => {
            list.append(`
                <tr id="group-row-${group.id}">
                    <td><input type="text" class="form-control form-control-sm" value="${escapeHtml(group.name)}" onchange="updateGroup(${group.id}, {name: this.value})"></td>
                    <td><input type="text" class="form-control form-control-sm" value="${escapeHtml(group.curationNotes || '')}" onchange="updateGroup(${group.id}, {curationNotes: this.value})"></td>
                    <td class="text-center">
                        <input type="checkbox" ${group.includeInReport ? 'checked' : ''} onchange="updateGroup(${group.id}, {includeInReport: this.checked})">
                    </td>
                    <td>${group.memberCount}</td>
                    <td>
                        <button class="btn btn-danger btn-sm" onclick="deleteGroup(${group.id})">Delete</button>
                    </td>
                </tr>
            `);
        });
    }).fail(function () {
        list.html('<tr><td colspan="5" class="text-danger">Failed to load groups.</td></tr>');
    });
}

/**
 * Fetch auto-suggestions for grouping files
 */
function suggestFileGroups() {
    $('#suggestedGroupsContainer').show();
    let list = $('#suggestedGroupsList');
    list.html('<tr><td colspan="3">Searching for identical finding sets...</td></tr>');

    $.getJSON(`api/v1/uploads/${uploadId}/filegroups/suggest`, function (data) {
        currentSuggestions = data;
        list.empty();
        if (data.length === 0) {
            list.append('<tr><td colspan="3" class="text-center">No new grouping suggestions found.</td></tr>');
            return;
        }
        data.forEach((sugg, index) => {
            let licenses = sugg.licenseSet && sugg.licenseSet.length > 0 ? sugg.licenseSet.join(', ') : '<i>No licenses</i>';
            list.append(`
                <tr>
                    <td>${sugg.memberCount} files</td>
                    <td>${licenses}</td>
                    <td>
                        <button class="btn btn-success btn-sm" onclick="createGroupFromSuggestion(${index})">
                            Create Group
                        </button>
                    </td>
                </tr>
            `);
        });
    }).fail(function () {
        list.html('<tr><td colspan="3" class="text-danger">Failed to get suggestions.</td></tr>');
    });
}

/**
 * Handle creation of a group from a suggestion
 */
function createGroupFromSuggestion(index) {
    let sugg = currentSuggestions[index];
    if (!sugg) return;

    let licenses = sugg.licenseSet && sugg.licenseSet.length > 0 ? sugg.licenseSet.join(', ') : "no licenses";
    let defaultName = "Group with " + licenses;
    let name = prompt("Enter group name:", defaultName);

    if (!name) return;

    $.ajax({
        url: `api/v1/uploads/${uploadId}/filegroups`,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            name: name,
            curationNotes: "Auto-suggested based on identical licenses: " + licenses,
            includeInReport: true
        }),
        success: function (resp) {
            let groupId = resp.message;
            // Add members
            $.ajax({
                url: `api/v1/uploads/${uploadId}/filegroups/${groupId}/members`,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ members: sugg.fileList }),
                success: function () {
                    refreshGroups();
                    suggestFileGroups(); // Refresh suggestions to remove already grouped files
                }
            }).fail(function () {
                alert("Group created but failed to add members.");
                refreshGroups();
            });
        },
        error: function (xhr) {
            alert("Failed to create group: " + (xhr.responseJSON ? xhr.responseJSON.message : xhr.statusText));
        }
    });
}

/**
 * Update group metadata
 */
function updateGroup(groupId, patch) {
    $.ajax({
        url: `api/v1/uploads/${uploadId}/filegroups/${groupId}`,
        method: 'PATCH',
        contentType: 'application/json',
        data: JSON.stringify(patch),
        error: function () {
            alert("Failed to update group.");
            refreshGroups();
        }
    });
}

/**
 * Delete a group
 */
function deleteGroup(groupId) {
    if (!confirm('Are you sure you want to delete this group? Files will be removed from the group but not deleted.')) {
        return;
    }

    $.ajax({
        url: `api/v1/uploads/${uploadId}/filegroups/${groupId}`,
        method: 'DELETE',
        success: function () {
            refreshGroups(); // Assuming refreshGroups() is the correct function to call
        },
        error: function () {
            alert("Failed to delete group.");
        }
    });
}

/**
 * Simple HTML escape
 */
function escapeHtml(text) {
    if (!text) return "";
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
