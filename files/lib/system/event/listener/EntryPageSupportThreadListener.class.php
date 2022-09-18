<?php
namespace show\system\event\listener;
use wbb\data\thread\Thread;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\WCF;

/**
 * Gets the support thread of an entry.
 *
 * @author		2020-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.show.supportThread
 */
class EntryPageSupportThreadListener implements IParameterizedEventListener {
	/**
	 * @inheritDoc
	 */
	public function execute($eventObj, $className, $eventName, array &$parameters) {
		if ($eventObj->entry->supportThreadID) {
			WCF::getTPL()->assign([
					'supportThread' => new Thread($eventObj->entry->supportThreadID)
			]);
		}
	}
}
