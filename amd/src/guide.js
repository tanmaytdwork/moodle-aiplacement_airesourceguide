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
            args: {}
        }])[0].then(function(result) {
            if (result.status) {
                // Policy accepted, proceed.
                openDrawer();
            } else {
                // Show policy acceptance, then proceed.
                showPolicyModal().then(function() {
                    openDrawer();
                    return;
                }).catch(function() {
                    // User declined policy, do nothing.
                });
            }
            return;
        }).catch(function() {
            // If policy check fails, try to proceed anyway.
            openDrawer();
        });
    }

    /**
     * Show the AI policy acceptance modal.
     *
     * @return {Promise} Resolves when user accepts, rejects when declined.
     */
    function showPolicyModal() {
        return new Promise(function(resolve, reject) {
            Str.get_strings([
                {key: 'aipolicy_title', component: 'aiplacement_airesourceguide'},
                {key: 'aipolicy_message', component: 'aiplacement_airesourceguide'},
                {key: 'accept', component: 'core'},
                {key: 'cancel', component: 'core'}
            ]).then(function(strings) {
                Notification.confirm(
                    strings[0],
                    strings[1],
                    strings[2],
                    strings[3],
                    function() {
                        // User accepted.
                        Ajax.call([{
                            methodname: 'core_ai_set_policy_status',
                            args: {}
                        }])[0].then(function() {
                            resolve();
                            return;
                        }).catch(reject);
                    },
                    function() {
                        reject();
                    }
                );
                return;
            }).catch(reject);
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
     */
    function fetchReferences() {
        Ajax.call([{
            methodname: 'aiplacement_airesourceguide_get_references',
            args: {cmid: cmid}
        }])[0].then(function(response) {
            // Render the results.
            return Templates.render('aiplacement_airesourceguide/results', {
                concepts: response.concepts,
                hasresults: response.concepts.length > 0
            });
        }).then(function(html) {
            var resultsArea = drawer.querySelector('.airesguide-results');
            if (resultsArea) {
                resultsArea.innerHTML = html;
            }
            return;
        }).catch(function(error) {
            var resultsArea = drawer.querySelector('.airesguide-results');
            if (resultsArea) {
                Str.get_string('error_generating', 'aiplacement_airesourceguide').then(function(errorMsg) {
                    resultsArea.innerHTML = '<div class="alert alert-danger">' +
                        '<i class="fa fa-exclamation-triangle mr-2"></i>' + errorMsg + '</div>';
                    return;
                }).catch(Notification.exception);
            }
            Notification.exception(error);
        });
    }
});
