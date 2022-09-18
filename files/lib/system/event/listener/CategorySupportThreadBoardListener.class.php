<?php
namespace show\system\event\listener;
use wbb\data\board\BoardCache;
use wbb\data\board\BoardNodeList;
use wcf\acp\form\AbstractCategoryEditForm;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\exception\UserInputException;
use wcf\system\WCF;

/**
 * Handles board selection for entry category.
 * 
 * @author		2020-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.show.supportThread
 */
class CategorySupportThreadBoardListener implements IParameterizedEventListener {
	/**
	 * data
	 */
	protected $boardID = 0;
	protected $boardNodeList;
	
	/**
	 * @inheritDoc
	 */
	public function execute($eventObj, $className, $eventName, array &$parameters) {
		// skip if one board for all
		if (SHOW_SUPPORT_THREAD_SINGLE_BOARD) {
			return;
		}
		
		// get boards
		if ($this->boardNodeList === null) {
			$this->boardNodeList = new BoardNodeList();
			$this->boardNodeList->readNodeTree();
		}
		
		switch ($eventName) {
			case 'assignVariables':
				WCF::getTPL()->assign([
						'supportThreadBoardNodeList' => $this->boardNodeList->getNodeList(),
						'supportThreadBoardID' => $this->boardID
				]);
			break;
			
			case 'readData':
				if (empty($_POST) && $eventObj instanceof AbstractCategoryEditForm) {
					$this->boardID = ($eventObj->category->supportThreadBoardID ?: 0);
				}
			break;
			
			case 'save':
				$eventObj->additionalData['supportThreadBoardID'] = $this->boardID;
			break;
			
			case 'validate':
				$this->boardID = (!empty($_POST['supportThreadBoardID'])) ? intval($_POST['supportThreadBoardID']) : 0;
				if ($this->boardID) {
					$board = BoardCache::getInstance()->getBoard($this->boardID);
					if ($board === null || !$board->isBoard()) {
						throw new UserInputException('supportThreadBoardID', 'invalid');
					}
				}
			break;
		}
	}
}
