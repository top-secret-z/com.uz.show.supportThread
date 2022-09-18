<?php

/*
 * Copyright by Udo Zaydowicz.
 * Modified by SoftCreatR.dev.
 *
 * License: http://opensource.org/licenses/lgpl-license.php
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
namespace show\system\event\listener;

use show\data\entry\Entry;
use show\data\entry\EntryAction;
use show\form\EntryAddForm;
use show\form\EntryEditForm;
use wbb\data\board\BoardCache;
use wbb\data\thread\ThreadEditor;
use wbb\system\cache\runtime\ThreadRuntimeCache;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\label\LabelHandler;

/**
 * Synchronizes entry labels with support thread.
 */
class AssignLabelSupportThreadListener implements IParameterizedEventListener
{
    /**
     * @inheritDoc
     */
    public function execute($eventObj, $className, $eventName, array &$parameters)
    {
        if ($eventObj instanceof EntryAction) {
            if ($eventObj->getActionName() == 'assignLabel') {
                foreach ($eventObj->getObjects() as $entry) {
                    if (!empty($entry->getCategory()->getLabelGroups())) {
                        if ($entry->supportThreadID) {
                            $this->updateThreadLabels($entry->supportThreadID, $eventObj->getParameters()['labelIDs'], \array_keys($entry->getCategory()->getLabelGroups()));
                        }
                    }
                }
            }
        } elseif ($eventObj instanceof EntryEditForm) {
            if (!empty($eventObj->entry->getCategory()->getLabelGroups())) {
                if ($eventObj->entry->supportThreadID) {
                    $this->updateThreadLabels($eventObj->entry->supportThreadID, $eventObj->labelIDs, \array_keys($eventObj->entry->getCategory()->getLabelGroups()));
                }
            }
        } elseif ($eventObj instanceof EntryAddForm) {
            $entry = $eventObj->objectAction->getReturnValues()['returnValues'];
            $entry = new Entry($entry->entryID); // reload entry
            if (!empty($entry->getCategory()->getLabelGroups())) {
                if ($entry->supportThreadID && !empty($entry->getCategory()->getLabelGroups())) {
                    $this->updateThreadLabels($entry->supportThreadID, $eventObj->labelIDs, \array_keys($entry->getCategory()->getLabelGroups()));
                }
            }
        }
    }

    /**
     * Updates the labels of a support thread.
     */
    private function updateThreadLabels($threadID, array $labelIDs, array $availableLabelGroupIDs)
    {
        $thread = ThreadRuntimeCache::getInstance()->getObject($threadID);
        $labelGroupIDs = BoardCache::getInstance()->getLabelGroupIDs($thread->boardID);
        $availableLabelGroupIDs = \array_intersect($availableLabelGroupIDs, $labelGroupIDs);

        if (!empty($availableLabelGroupIDs)) {
            // remove unavailable labels
            foreach ($labelIDs as $groupID => $labelID) {
                if (!\in_array($groupID, $labelGroupIDs)) {
                    unset($labelIDs[$groupID]);
                }
            }

            // update labels
            LabelHandler::getInstance()->replaceLabels(
                $availableLabelGroupIDs,
                $labelIDs,
                'com.woltlab.wbb.thread',
                $threadID
            );

            // update hasLabels
            $assignedLabels = LabelHandler::getInstance()->getAssignedLabels(LabelHandler::getInstance()->getObjectType('com.woltlab.wbb.thread')->objectTypeID, [$threadID]);
            $editor = new ThreadEditor($thread);
            $editor->update(['hasLabels' => empty($assignedLabels[$threadID]) ? 0 : 1]);
        }
    }
}
