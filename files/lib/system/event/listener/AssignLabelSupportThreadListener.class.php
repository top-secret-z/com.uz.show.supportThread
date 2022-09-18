<?php
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
 *
 * @author		2020-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.show.supportThread
 */
class AssignLabelSupportThreadListener implements IParameterizedEventListener {
	/**
	 * @inheritDoc
	 */
	public function execute($eventObj, $className, $eventName, array &$parameters) {
		if ($eventObj instanceof EntryAction) {
			if ($eventObj->getActionName() == 'assignLabel') {
				foreach ($eventObj->getObjects() as $entry) {
					if (!empty($entry->getCategory()->getLabelGroups())) {
						if ($entry->supportThreadID) {
							$this->updateThreadLabels($entry->supportThreadID, $eventObj->getParameters()['labelIDs'], array_keys($entry->getCategory()->getLabelGroups()));
						}
					}
				}
			}
		}
		else if ($eventObj instanceof EntryEditForm) {
			if (!empty($eventObj->entry->getCategory()->getLabelGroups())) {
				if ($eventObj->entry->supportThreadID) {
					$this->updateThreadLabels($eventObj->entry->supportThreadID, $eventObj->labelIDs, array_keys($eventObj->entry->getCategory()->getLabelGroups()));
				}
			}
		}
		else if ($eventObj instanceof EntryAddForm) {
			$entry = $eventObj->objectAction->getReturnValues()['returnValues'];
			$entry = new Entry($entry->entryID); // reload entry
			if (!empty($entry->getCategory()->getLabelGroups())) {
				if ($entry->supportThreadID && !empty($entry->getCategory()->getLabelGroups())) {
					$this->updateThreadLabels($entry->supportThreadID, $eventObj->labelIDs, array_keys($entry->getCategory()->getLabelGroups()));
				}
			}
		}
	}
	
	/**
	 * Updates the labels of a support thread.
	 */
	private function updateThreadLabels($threadID, array $labelIDs, array $availableLabelGroupIDs) {
		$thread = ThreadRuntimeCache::getInstance()->getObject($threadID);
		$labelGroupIDs = BoardCache::getInstance()->getLabelGroupIDs($thread->boardID);
		$availableLabelGroupIDs = array_intersect($availableLabelGroupIDs, $labelGroupIDs);
		
		if (!empty($availableLabelGroupIDs)) {
			// remove unavailable labels
			foreach ($labelIDs as $groupID => $labelID) {
				if (!in_array($groupID, $labelGroupIDs)) {
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
