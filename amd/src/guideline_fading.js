/**
 * Scripting-forum module
 *
 * @package    mod_sforum
 * @copyright  Geiser Chalco <geiser@usp.br>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_sforum/guideline_fading
 */
define(["jquery"], function($) {
    
    var hiddenGuidelineOpacity = 0.3;
    
    return /** @alias module:mod_sforum/guideline_fading */ {

        setHiddenGuidelineOpacity: function(opacity) {
            hiddenGuidelineOpacity = opacity;
        },

        /**
         * Initialize the guideline fading.
         *
         * @param {nextSteps} array of ids or alias for the next steps
         */
        init: function(nextSteps) {
            $(".guideline-step").fadeTo("fast", 1);
            if (nextSteps != undefined && nextSteps.length>0) {
                var nexts = "";
                for (var i = 0; i < nextSteps.length; i++) {
                    nexts = nexts + ", ."+nextSteps[i];
                }
                nexts = nexts.substr(2);
                $(".guideline-step").not(nexts).fadeTo('fast', hiddenGuidelineOpacity);
            } else {
                $(".guideline-step").fadeTo('fast', hiddenGuidelineOpacity);
            }
        }

    };

});

