<?php

/**
 * DokuWiki Plugin saveandedit (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Hamann <michael@content-space.de>
 */

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;

class action_plugin_saveandedit extends ActionPlugin
{
    /** The action that has been handled before the current action */
    private $previousAct;

    public function register(EventHandler $controller)
    {
       // try to register our handler at a late position so e.g. the edittable plugin has a possibility to process its
        // data
        $controller->register_hook(
            'ACTION_ACT_PREPROCESS',
            'BEFORE',
            $this,
            'handleActionActPreprocess',
            null,
            1000
        );
        $controller->register_hook('FORM_EDIT_OUTPUT', 'BEFORE', $this, 'handleHtmlEditFormOutput');
    }

    /**
     * Clean the environment after saving for the next edit.
     */
    private function cleanAfterSave()
    {
        global $ID, $INFO, $REV, $RANGE, $TEXT, $PRE, $SUF;
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
        p_set_metadata($ID, [], true);
        $INFO = pageinfo(); // reset pageinfo to new data (e.g. if the page exists)
    }

    public function handleActionActPreprocess(Event $event, $param)
    {
        global $INPUT;

        if (!$INPUT->bool('saveandedit')) {
            return;
        }

        // check if the action was given as array key
        if (is_array($event->data)) {
            [$act] = array_keys($event->data);
        } else {
            $act = $event->data;
        }

        // Greebo and above
        if (class_exists('\\dokuwiki\\ActionRouter', false)) {
            /*
               The ACTION_ACT_PREPROCESS event is triggered several
               times, once for every action. After the save has been
               executed, the next event is 'draftdel'. We intercept
               the 'draftdel' action and replace it by 'edit'. As this
               is a logical place where other plugins may want to save
               data (e.g. blogtng), we try to be handled relatively
               late. To fix plugins that want to handle the 'edit'
               action, we trigger a new event for the 'edit' action.
            */
            if ($this->previousAct === 'save' && $act === 'draftdel') {
                $this->cleanAfterSave();
                $event->data = 'edit';

                /*
                   The edittable plugin would restore $TEXT from the
                   edittable_data post data on each
                   ACTION_ACT_PREPROCESS call. This breaks the
                   automatic restore of the prefix and suffix
                   data. Stop it from doing this by unsetting its
                   data.
                */
                $INPUT->post->remove('edittable_data');

                /*
                   Stop propagation of the event. All subsequent event
                   handlers will be called anyway again by the event
                   triggered below.
                */
                $event->stopPropagation();

                /*
                   Trigger a new event for the edit action.
                   This ensures that all event handlers for the edit
                   action are called.  However, we only advise the
                   before handlers and re-use the default action and
                   the after handling of the original event.
                */
                $new_evt = new Event('ACTION_ACT_PREPROCESS', $event->data);
                // prevent the default action of the original event
                if (!$new_evt->advise_before()) {
                    $event->preventDefault();
                }
            }
            $this->previousAct = $act;
            // pre-Greebo compatibility
        } elseif ($act === 'save' && actionOK($act) && act_permcheck($act) == 'save' && checkSecurityToken()) {
            $event->data = act_save($act);
            if ($event->data === 'show') {
                $event->data = 'edit';
                $this->cleanAfterSave();
            } elseif ($event->data === 'conflict') {
                // DokuWiki won't accept 'conflict' as action here.
                // Just execute save again, the conflict will be detected again
                $event->data = 'save';
            }
        }
    }

    public function handleHtmlEditFormOutput(Event $event, $param)
    {
        global $INPUT;

        $form = $event->data;
        $pos = $form->findPositionByAttribute('type', 'submit');

        if (!$pos) {
            // no submit button found, source view
            return;
        }

        --$pos;

        $form->addTagOpen('div', $pos++);
        $attrs = $INPUT->bool('saveandedit') ? ['checked' => 'checked'] : [];

        $cb = $form->addCheckBox('saveandedit', $this->getLang('btn_saveandedit'), $pos++);
        $cb->attrs = $attrs;
        $form->addtagClose('div', $pos);
    }
}

// vim:ts=4:sw=4:et:
