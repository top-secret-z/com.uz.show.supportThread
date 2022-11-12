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
use show\data\entry\EntryEditor;
use wbb\data\board\BoardCache;
use wbb\data\post\PostAction;
use wbb\data\thread\Thread;
use wbb\data\thread\ThreadAction;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\html\input\HtmlInputProcessor;
use wcf\system\language\LanguageFactory;
use wcf\system\tagging\TagEngine;

/**
 * Creates the support thread.
 */
class EntryActionSupportThreadListener implements IParameterizedEventListener
{
    /**
     * @inheritDoc
     */
    public function execute($eventObj, $className, $eventName, array &$parameters)
    {
        if ($eventObj->getActionName() == 'triggerPublication') {
            // check board ids
            $board = null;
            $categoryIDs = [];

            if (SHOW_SUPPORT_THREAD_SINGLE_BOARD) {
                $board = BoardCache::getInstance()->getBoard(SHOW_SUPPORT_THREAD_BOARD_ID);
                if ($board === null || !$board->isBoard()) {
                    return;
                }

                if (SHOW_SUPPORT_THREAD_CATEGORIES) {
                    $categoryIDs = \explode("\n", SHOW_SUPPORT_THREAD_CATEGORIES);
                }
            }

            foreach ($eventObj->getObjects() as $entry) {
                $entry = new EntryEditor(new Entry($entry->entryID));

                // check categories
                if (!empty($categoryIDs) || !SHOW_SUPPORT_THREAD_SINGLE_BOARD) {
                    $result = false;

                    if (SHOW_SUPPORT_THREAD_SINGLE_BOARD) {
                        if (\in_array($entry->categoryID, $categoryIDs)) {
                            $result = true;
                        }
                    } else {
                        if ($entry->getCategory()->supportThreadBoardID) {
                            $board = BoardCache::getInstance()->getBoard($entry->getCategory()->supportThreadBoardID);
                            if ($board === null || !$board->isBoard()) {
                                $board = null;
                            } else {
                                $result = true;
                            }
                        }
                    }

                    if (!$result) {
                        continue;
                    }
                }

                // Entry thread
                if ($entry->supportThreadID) {
                    continue;
                }

                // language
                if ($entry->languageID) {
                    $language = LanguageFactory::getInstance()->getLanguage($entry->languageID);
                } else {
                    $language = LanguageFactory::getInstance()->getDefaultLanguage();
                }

                // tags
                $tags = [];
                if (MODULE_TAGGING) {
                    $tagObjects = TagEngine::getInstance()->getObjectTags(
                        'com.uz.show.entry',
                        $entry->entryID,
                        [$entry->languageID === null ? LanguageFactory::getInstance()->getDefaultLanguageID() : ""]
                    );

                    foreach ($tagObjects as $tagObject) {
                        $tags[] = $tagObject->getTitle();
                    }
                }

                // thread
                $htmlInputProcessor = new HtmlInputProcessor();
                $htmlInputProcessor->process($language->getDynamicVariable('show.entry.supportThread.message', ['entry' => $entry->getDecoratedObject()]), 'com.woltlab.wbb.post');
                $threadData = [
                    'data' => [
                        'boardID' => $board->boardID,
                        'languageID' => (\count(LanguageFactory::getInstance()->getContentLanguages()) ? $entry->languageID : null),
                        'topic' => $language->getDynamicVariable('show.entry.supportThread.subject', ['entry' => $entry->getDecoratedObject()]),
                        'time' => $entry->time,
                        'userID' => $entry->userID,
                        'username' => $entry->username,
                    ],
                    'postData' => [],
                    'board' => $board,
                    'tags' => $tags,
                    'htmlInputProcessor' => $htmlInputProcessor,
                ];
                $objectAction = new ThreadAction([], 'create', $threadData);
                $resultValues = $objectAction->executeAction();

                // update support thread id
                $entryEditor = $entry;
                $entryEditor->update(['supportThreadID' => $resultValues['returnValues']->threadID]);

                // mark thread as read
                $threadAction = new ThreadAction([$resultValues['returnValues']], 'markAsRead');
                $threadAction->executeAction();
            }
        }

        // update
        if ($eventObj->getActionName() == 'update') {
            foreach ($eventObj->getObjects() as $entry) {
                if ($entry->supportThreadID) {
                    $thread = new Thread($entry->supportThreadID);
                    $post = $thread->getFirstPost();
                    $entry = new EntryEditor(new Entry($entry->entryID));

                    // get language
                    if ($entry->languageID) {
                        $language = LanguageFactory::getInstance()->getLanguage($entry->languageID);
                    } else {
                        $language = LanguageFactory::getInstance()->getDefaultLanguage();
                    }

                    // get tags
                    $tags = [];
                    if (MODULE_TAGGING) {
                        $tagObjects = TagEngine::getInstance()->getObjectTags(
                            'com.uz.show.entry',
                            $entry->entryID,
                            [$entry->languageID === null ? LanguageFactory::getInstance()->getDefaultLanguageID() : ""]
                        );
                        foreach ($tagObjects as $tagObject) {
                            $tags[] = $tagObject->getTitle();
                        }
                    }
                    $threadAction = new ThreadAction([$thread], 'update', [
                        'data' => [
                            'tags' => $tags,
                            'languageID' => (\count(LanguageFactory::getInstance()->getContentLanguages()) ? $entry->languageID : null),
                            'topic' => $language->getDynamicVariable('show.entry.supportThread.subject', ['entry' => $entry->getDecoratedObject()]),
                        ],
                    ]);
                    $threadAction->executeAction();
                    $htmlInputProcessor = new HtmlInputProcessor();
                    $htmlInputProcessor->process($language->getDynamicVariable('show.entry.supportThread.message', ['entry' => $entry->getDecoratedObject()]), 'com.woltlab.wbb.post');
                    $postAction = new PostAction([$post], 'update', ['htmlInputProcessor' => $htmlInputProcessor]);
                    $postAction->executeAction();
                }
            }
        }

        // disable
        if ($eventObj->getActionName() == 'disable') {
            foreach ($eventObj->getObjects() as $entry) {
                if ($entry->supportThreadID) {
                    $thread = new Thread($entry->supportThreadID);
                    $threadAction = new ThreadAction([$thread], 'disable');
                    $threadAction->executeAction();
                }
            }
        }

        // enable
        if ($eventObj->getActionName() == 'enable') {
            foreach ($eventObj->getObjects() as $entry) {
                if ($entry->supportThreadID) {
                    $thread = new Thread($entry->supportThreadID);
                    $threadAction = new ThreadAction([$thread], 'enable');
                    $threadAction->executeAction();
                }
            }
        }

        // trash
        if ($eventObj->getActionName() == 'trash') {
            foreach ($eventObj->getObjects() as $entry) {
                if ($entry->supportThreadID) {
                    $thread = new Thread($entry->supportThreadID);
                    $threadAction = new ThreadAction([$thread], 'trash');
                    $threadAction->executeAction();
                }
            }
        }

        // restore
        if ($eventObj->getActionName() == 'restore') {
            foreach ($eventObj->getObjects() as $entry) {
                if ($entry->supportThreadID) {
                    $thread = new Thread($entry->supportThreadID);
                    $threadAction = new ThreadAction([$thread], 'restore');
                    $threadAction->executeAction();
                }
            }
        }

        //delete
        if ($eventObj->getActionName() == 'delete') {
            foreach ($eventObj->getObjects() as $entry) {
                if ($entry->supportThreadID) {
                    $thread = new Thread($entry->supportThreadID);
                    $threadAction = new ThreadAction([$thread], 'delete');
                    $threadAction->executeAction();
                }
            }
        }
    }
}
