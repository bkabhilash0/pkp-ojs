<?php

/**
 * @file controllers/grid/files/SubmissionFilesCategoryGridDataProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFilesCategoryGridDataProvider
 *
 * @ingroup controllers_grid_files_review
 *
 * @brief Provide access to submission files data for category grids.
 */

namespace PKP\controllers\grid\files;

use APP\core\Application;
use APP\facades\Repo;
use PKP\controllers\grid\CategoryGridDataProvider;
use PKP\db\DAORegistry;
use PKP\submissionFile\SubmissionFile;

class SubmissionFilesCategoryGridDataProvider extends CategoryGridDataProvider
{
    /** @var array */
    public $_submissionFiles;


    /**
     * Constructor
     *
     * @param int $fileStage The current file stage that the grid is handling
     * (others file stages could be shown activating the grid filter, but this
     * is the file stage that will be used to bring files from other stages, upload
     * new file, etc).
     * @param array $dataProviderInitParams Other parameters to initiate the grid
     * data provider that this category grid data provider will use to implement
     * common behaviours and data.
     */
    public function __construct($fileStage, $dataProviderInitParams = null)
    {
        parent::__construct();
        $this->setDataProvider($this->initGridDataProvider($fileStage, $dataProviderInitParams));
    }


    //
    // Extended method from CategoryGridDataProvider.
    //
    /**
     * @copydoc CategoryGridDataProvider::setDataProvider()
     */
    public function setDataProvider($gridDataProvider)
    {
        assert($gridDataProvider instanceof SubmissionFilesGridDataProvider);
        parent::setDataProvider($gridDataProvider);
    }


    //
    // Implement template methods from GridDataProvider
    //
    /**
     * @copydoc GridDataProvider::getAuthorizationPolicy()
     */
    public function getAuthorizationPolicy($request, $args, $roleAssignments)
    {
        // Get the submission files grid data provider authorization policy.
        $dataProvider = $this->getDataProvider();
        return $dataProvider->getAuthorizationPolicy($request, $args, $roleAssignments);
    }

    /**
     * @copydoc GridDataProvider::getRequestArgs()
     */
    public function getRequestArgs()
    {
        $dataProvider = $this->getDataProvider();
        return $dataProvider->getRequestArgs();
    }

    /**
     * @copydoc GridDataProvider::loadData()
     */
    public function loadData($filter = [])
    {
        // Return only the user accessible workflow stages.
        return array_keys($this->getAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES));
    }


    //
    // Implement template methods from CategoryGridDataProvider
    //
    /**
     * @copydoc CategoryGridDataProvider::loadCategoryData()
     *
     * @param null|mixed $filter
     * @param null|mixed $reviewRound
     */
    public function loadCategoryData($request, $categoryDataElement, $filter = null, $reviewRound = null)
    {
        /** @var SubmissionFilesGridDataProvider */
        $dataProvider = $this->getDataProvider();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $stageId = $categoryDataElement;
        $fileStages = $this->_getFileStagesByStageId($stageId);
        $stageSubmissionFiles = null;

        // For review stages, get the revisions of the review round that user is currently accessing.
        if ($stageId == WORKFLOW_STAGE_ID_INTERNAL_REVIEW || $stageId == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
            if (is_null($reviewRound) || $reviewRound->getStageId() != $stageId) {
                $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
                $reviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $stageId);
            }
            if ($reviewRound) {
                $stageSubmissionFiles = Repo::submissionFile()
                    ->getCollector()
                    ->filterBySubmissionIds([$submission->getId()])
                    ->filterByReviewRoundIds([$reviewRound->getId()])
                    ->filterByFileStages($fileStages)
                    ->getMany()
                    ->toArray();
            } else {
                $stageSubmissionFiles = [];
            }
        } else {
            // Filter the passed workflow stage files.
            if (!$this->_submissionFiles) {
                $this->_submissionFiles = Repo::submissionFile()
                    ->getCollector()
                    ->filterBySubmissionIds([$submission->getId()])
                    ->getMany()
                    ->toArray();
            }
            $submissionFiles = $this->_submissionFiles;
            $stageSubmissionFiles = [];
            foreach ($submissionFiles as $key => $submissionFile) {
                if (in_array($submissionFile->getData('fileStage'), $fileStages)) {
                    $stageSubmissionFiles[$key] = $submissionFile;
                } elseif ($submissionFile->getData('fileStage') == SubmissionFile::SUBMISSION_FILE_QUERY) {
                    // Determine the stage from the query.
                    if ($submissionFile->getData('assocType') != Application::ASSOC_TYPE_NOTE) {
                        break;
                    }
                    $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
                    $note = $noteDao->getById($submissionFile->getData('assocId'));
                    $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
                    if ($note && $note->getAssocType() == Application::ASSOC_TYPE_QUERY) {
                        $query = $queryDao->getById($note->getAssocId());
                    }
                    if ($query && $query->getStageId() == $stageId) {
                        $stageSubmissionFiles[$key] = $submissionFile;
                    }
                }
            }
        }
        return $dataProvider->prepareSubmissionFileData($stageSubmissionFiles, false, $filter);
    }


    //
    // Public methods
    //
    /**
     * @copydoc SubmissionFilesGridDataProvider::getAddFileAction()
     */
    public function getAddFileAction($request)
    {
        /** @var SubmissionFilesGridDataProvider */
        $dataProvider = $this->getDataProvider();
        return $dataProvider->getAddFileAction($request);
    }

    /**
     * @copydoc SubmissionFilesGridDataProvider::getFileStage()
     */
    public function setStageId($stageId)
    {
        /** @var SubmissionFilesGridDataProvider */
        $dataProvider = $this->getDataProvider();
        $dataProvider->setStageId($stageId);
    }

    /**
     * @copydoc SubmissionFilesGridDataProvider::getFileStage()
     */
    public function getFileStage()
    {
        /** @var SubmissionFilesGridDataProvider */
        $dataProvider = $this->getDataProvider();
        return $dataProvider->getFileStage();
    }


    //
    // Protected methods.
    //
    /**
     * Init the grid data provider that this category grid data provider
     * will use and return it. Override this to initiate another grid data provider.
     *
     * @param int $fileStage
     * @param array $initParams (optional) The parameters to initiate the grid data provider.
     *
     * @return SubmissionFilesGridDataProvider
     */
    public function initGridDataProvider($fileStage, $initParams = null)
    {
        // By default, this category grid data provider use the
        // SubmissionFilesGridDataProvider.
        return new SubmissionFilesGridDataProvider($fileStage);
    }


    //
    // Private helper methods.
    //
    /**
     * Get the file stage using the passed stage id. This will define
     * which file stage will be present on each workflow stage category
     * of the grid.
     */
    public function _getFileStagesByStageId(int $stageId): array
    {
        switch ($stageId) {
            case WORKFLOW_STAGE_ID_SUBMISSION:
                return [SubmissionFile::SUBMISSION_FILE_SUBMISSION];
                break;
            case WORKFLOW_STAGE_ID_INTERNAL_REVIEW:
                return [SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE, SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION];
                break;
            case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
                return [SubmissionFile::SUBMISSION_FILE_REVIEW_FILE, SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION];
                break;
            case WORKFLOW_STAGE_ID_EDITING:
                return [SubmissionFile::SUBMISSION_FILE_FINAL, SubmissionFile::SUBMISSION_FILE_COPYEDIT];
                break;
            case WORKFLOW_STAGE_ID_PRODUCTION:
                return [SubmissionFile::SUBMISSION_FILE_PRODUCTION_READY];
                break;
            default:
                return [];
        }
    }
}
