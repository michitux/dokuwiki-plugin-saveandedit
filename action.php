<?php
/**
 * DokuWiki Plugin saveandedit (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Hamann <michael@content-space.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'action.php';

class action_plugin_saveandedit extends DokuWiki_Action_Plugin {

    function register(&$controller) {
       $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_action_act_preprocess');
       $controller->register_hook('HTML_EDITFORM_OUTPUT', 'BEFORE', $this, 'handle_html_editform_output');
    }

    function handle_action_act_preprocess(&$event, $param) {
        global $ID, $INFO, $REV, $RANGE, $TEXT, $PRE, $SUF;

        $event->data = act_clean($event->data);
        if ($event->data == 'save' && $_REQUEST['saveandedit']) {
            $event->data = act_permcheck($event->data);
            if ($event->data == 'save' && checkSecurityToken()) {
                $event->data = act_save($event->data);
                if ($event->data == 'show') {
                    $event->data = 'edit';
                    $REV = ''; // now we are working on the current revision
                    // Handle section edits
                    if ($PRE || $SUF) { 
                        // $from and $to are 1-based indexes of the actually edited content
                        $from = strlen($PRE) + 1;
                        $to = $from + strlen($TEXT);
                        $RANGE = $from . '-' . $to;
                    }
                    // Ensure the current text is loaded again from the file
                    unset($GLOBALS['TEXT'], $GLOBALS['PRE'], $GLOBALS['SUF']);
                    // Reset the date of the last modification to avoid conflict messages
                    unset($GLOBALS['DATE']);
                    // Reset the change check
                    unset($_REQUEST['changecheck']);
                    // Force rendering of the metadata in order to ensure metadata is correct
                    p_set_metadata($ID, array(), true);
                    $INFO = pageinfo(); // reset pageinfo to new data (e.g. if the page exists)
                } elseif ($event->data == 'conflict') {
                    // DokuWiki won't accept 'conflict' as action here.
                    // Just execute save again, the conflict will be detected again
                    $event->data = 'save';
                }
            }
        }
    }

    function handle_html_editform_output(&$event, $param) {
        $pos = $event->data->findElementByAttribute('type','submit');
        if(!$pos) return; // no submit button found, source view
        $pos -= 1;
        $event->data->insertElement($pos++, form_makeOpenTag('div', array()));
        $attrs = $_REQUEST['saveandedit'] ? array('checked' => 'checked') : array();
        $event->data->insertElement($pos++, form_makeCheckboxField('saveandedit', '1', $this->getLang('btn_saveandedit'), '', '', $attrs));
        $event->data->insertElement($pos++, form_makeCloseTag('div'));
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:
