<?php

namespace SimpleTasks\Hook;

use MediaWiki\MediaWikiServices;
use SimpleTasks\SimpleTask;
use SimpleTasks\SimpleTaskManager;
use SMW\DataValueFactory;
use SMW\SemanticData;
use SMW\Store;
use SMW\Subobject;

class AddSemanticSubobject {

	/**
	 * @param Store $store
	 * @param SemanticData $semanticData
	 * @return bool
	 */
	public static function onBeforeDataUpdateComplete( Store $store, SemanticData $semanticData ) {
		$subject = $semanticData->getSubject();
		if ( $subject->getNamespace() === NS_USER ) {
			return true;
		}

		$title = $subject->getTitle();
		$services = MediaWikiServices::getInstance();
		$titleFactory = $services->getTitleFactory();
		$wikiPage = $services->getWikiPageFactory()->newFromTitle( $title );
		$dataValueFactory = DataValueFactory::getInstance();

		/** @var SimpleTaskManager */
		$simpleTaskManagerService = $services->getService( 'SimpleTaskManager' );
		$simpleTaskManager = $simpleTaskManagerService->forTitle( $wikiPage->getTitle() );
		$simpleTasks = $simpleTaskManager->query();

		/** @var SimpleTask $simpleTask */
		foreach ( $simpleTasks as $simpleTask ) {
			$simpleTaskData = $simpleTask->jsonSerialize();
			$id = $simpleTaskData['id'];
			$task = $simpleTaskData['text'];
			$userName = $simpleTaskData['assignee'];
			$dueDateObj = $simpleTask->getDueDate();
			$dueDate = $dueDateObj ? $dueDateObj->format( 'Y-m-d' ) : '';
			$desc = "$task for $userName";
			$desc .= $dueDate ? " to be completed by $dueDate" : '';
			$statusBool = $simpleTaskData['completed'];
			$status = $statusBool ? 'done' : 'open';

			$subobjectName = "SimpleTask_$id";
			$subobject = new Subobject( $title );
			$subobject->setEmptyContainerForId( $subobjectName );

			$descValue = $dataValueFactory->newDataValueByText(
				'Task/Desc',
				$desc
			);
			$userTitle = $titleFactory->newFromText( $userName, NS_USER );
			$userValue = $dataValueFactory->newDataValueByText(
				'Task/User',
				$userTitle->getFullText()
			);
			if ( $dueDate ) {
				$dueDateValue = $dataValueFactory->newDataValueByText(
					'Task/Due date',
					$dueDate
				);
			}
			$statusValue = $dataValueFactory->newDataValueByText(
				'Task/Status',
				$status
			);

			$subobject->addDataValue( $descValue );
			$subobject->addDataValue( $userValue );
			if ( $dueDate ) {
				$subobject->addDataValue( $dueDateValue );
			}
			$subobject->addDataValue( $statusValue );

			$semanticData->addPropertyObjectValue(
				$subobject->getProperty(),
				$subobject->getContainer()
			);
		}

		return true;
	}
}
