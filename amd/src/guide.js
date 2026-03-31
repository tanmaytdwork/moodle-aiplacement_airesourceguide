// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AMD module for the AI Resource Guide placement.
 *
 * Handles button rendering, drawer UI, AI policy check,
 * AJAX communication, and result display.
 *
 * @module     aiplacement_airesourceguide/guide
 * @copyright  2026 Tanmay Deshmukh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'core/ajax',
    'core/notification',
    'core/templates',
    'core/str'
], function(Ajax, Notification, Templates, Str) {

    /** @var {number|null} cmid Course module ID. */
    var cmid = null;

    /** @var {HTMLElement|null} drawer The drawer element. */
    var drawer = null;

    return {

        /**
         * Initialize the AI Resource Guide.
         *
         * Called from lib.php via js_call_amd. Only runs on Page activities
         * where the user has the required capability.
         *
         * @param {number} cmidParam Course module ID.
         */
        init: function(cmidParam) {
            cmid = cmidParam;
            renderButton();
        }
    };

    /**
     * Render the "Find References" button after the page content.
     */
    function renderButton() {
        // Find the page content area, trying selectors in order of specificity.
        var selectors = [
            '#region-main [role="main"]',
            '#region-main .content',
            '[role="main"]',
            '#region-main',
        ];

        var contentArea = null;
        for (var i = 0; i < selectors.length; i++) {
            contentArea = document.querySelector(selectors[i]);
            if (contentArea) {
                break;
            }
        }

        if (!contentArea) {
            return;
        }

        Str.get_string('findreferences', 'aiplacement_airesourceguide').then(function(btnText) {
            var btn = document.createElement('button');
            btn.className = 'btn btn-outline-primary mt-3 mb-3 aiplacement-airesguide-btn';
            btn.setAttribute('type', 'button');
            btn.innerHTML = '<i class="fa fa-book mr-2"></i>' + btnText;

            // Insert after the content area.
            contentArea.appendChild(btn);

            btn.addEventListener('click', function() {
                handleButtonClick();
            });

            return;
        }).catch(Notification.exception);
    }

    /**
     * Handle the Find References button click.
     *
     * First checks the AI user policy status, then proceeds
     * to open the drawer and fetch references.
     */
    function handleButtonClick() {
        // Check AI policy status first.
        Ajax.call([{
            methodname: 'core_ai_get_policy_status',
            args: {userid: M.cfg.userId}
        }])[0].then(function(result) {
            handlePolicyResult(result.status);
            return null;
        }).catch(Notification.exception);
    }

    /**
     * Handle the policy check result.
     *
     * Opens the drawer immediately if already accepted, otherwise
     * shows the policy modal first.
     *
     * @param {boolean} policyAccepted Whether the user has already accepted.
     */
    function handlePolicyResult(policyAccepted) {
        if (policyAccepted) {
            openDrawer();
            return;
        }
        showPolicyModal().then(function() {
            openDrawer();
            return null;
        }).catch(function() {
            // User declined policy, do nothing.
        });
    }

    /**
     * Show the AI policy acceptance modal.
     *
     * @return {Promise} Resolves when user accepts, rejects when declined.
     */
    function showPolicyModal() {
        return Str.get_strings([
            {key: 'aipolicy_title', component: 'aiplacement_airesourceguide'},
            {key: 'aipolicy_message', component: 'aiplacement_airesourceguide'},
            {key: 'aipolicy_point1', component: 'aiplacement_airesourceguide'},
            {key: 'aipolicy_point2', component: 'aiplacement_airesourceguide'},
            {key: 'aipolicy_point3', component: 'aiplacement_airesourceguide'},
            {key: 'accept', component: 'core'},
            {key: 'cancel', component: 'core'}
        ]).then(buildConfirmModal);
    }

    /**
     * Build and display the policy confirmation modal.
     *
     * @param {Array} strings Resolved language strings.
     * @return {Promise} Resolves when user accepts, rejects when declined.
     */
    function buildConfirmModal(strings) {
        var message = strings[1] + '<br>' +
            '1. ' + strings[2] + '<br>' +
            '2. ' + strings[3] + '<br>' +
            '3. ' + strings[4];
        return new Promise(function(resolve, reject) {
            Notification.confirm(
                strings[0],
                message,
                strings[5],
                strings[6],
                function() {
                    // User accepted — record policy status.
                    Ajax.call([{
                        methodname: 'core_ai_set_policy_status',
                        args: {contextid: M.cfg.contextid}
                    }])[0].then(resolve).catch(reject);
                },
                reject
            );
        });
    }

    /**
     * Open the reference drawer.
     *
     * Creates the drawer element, slides it in from the right,
     * and triggers the reference fetch.
     */
    function openDrawer() {
        // Remove existing drawer if any.
        if (drawer) {
            drawer.remove();
        }

        // Create drawer container.
        drawer = document.createElement('div');
        drawer.className = 'aiplacement-airesguide-drawer';

        // Render drawer content from template.
        Templates.render('aiplacement_airesourceguide/drawer', {
            loading: true
        }).then(function(html) {
            drawer.innerHTML = html;
            document.body.appendChild(drawer);

            // Slide in with animation.
            requestAnimationFrame(function() {
                drawer.classList.add('show');
            });

            // Attach close button handler.
            var closeBtn = drawer.querySelector('.airesguide-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', closeDrawer);
            }

            // Fetch references.
            fetchReferences();
            return;
        }).catch(Notification.exception);
    }

    /**
     * Close the drawer with animation.
     */
    function closeDrawer() {
        if (drawer) {
            drawer.classList.remove('show');
            setTimeout(function() {
                if (drawer) {
                    drawer.remove();
                    drawer = null;
                }
            }, 300);
        }
    }

    /**
     * Fetch references from the web service.
     *
     * Pre-loads the error string so the catch handler stays promise-free.
     */
    function fetchReferences() {
        Str.get_string('error_generating', 'aiplacement_airesourceguide')
            .then(startReferenceFetch)
            .catch(Notification.exception);
    }

    /**
     * Execute the references Ajax call and render results.
     *
     * Receives the pre-loaded error string so it can display inline
     * errors without starting a new promise chain inside the catch.
     *
     * @param {string} errorMsg Localised error message for inline display.
     * @return {null}
     */
    function startReferenceFetch(errorMsg) {
        Ajax.call([{
            methodname: 'aiplacement_airesourceguide_get_references',
            args: {cmid: cmid}
        }])[0].then(function(response) {
            return Templates.render('aiplacement_airesourceguide/results', {
                concepts: response.concepts,
                hasresults: response.concepts.length > 0
            });
        }).then(function(html) {
            var resultsArea = drawer.querySelector('.airesguide-results');
            if (resultsArea) {
                resultsArea.innerHTML = html;
            }
            return null;
        }).catch(function(ajaxError) {
            Notification.exception(ajaxError);
            var resultsArea = drawer.querySelector('.airesguide-results');
            if (resultsArea) {
                resultsArea.innerHTML = '<div class="alert alert-danger">' +
                    '<i class="fa fa-exclamation-triangle mr-2"></i>' + errorMsg + '</div>';
            }
        });
        return null;
    }
});
