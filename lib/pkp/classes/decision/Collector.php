<?php
/**
 * @file classes/decision/Collector.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of editor decisions
 */

namespace PKP\decision;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\Hook;

class Collector implements CollectorInterface
{
    public DAO $dao;
    public ?array $decisionTypes = null;
    public ?array $editorIds = null;
    public ?array $reviewRoundIds = null;
    public ?array $rounds = null;
    public ?array $stageIds = null;
    public ?array $submissionIds = null;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    public function getCount(): int
    {
        return $this->dao->getCount($this);
    }

    public function getIds(): Collection
    {
        return $this->dao->getIds($this);
    }

    public function getMany(): LazyCollection
    {
        return $this->dao->getMany($this);
    }

    /**
     * Filter decisions by these decision types
     *
     * @param int[]|null $decisionTypes One of the Decision::* constants
     */
    public function filterByDecisionTypes(?array $decisionTypes): self
    {
        $this->decisionTypes = $decisionTypes;
        return $this;
    }

    /**
     * Filter decisions taken by one or more editors]
     *
     * @param int[]|null $editorIds
     */
    public function filterByEditorIds(?array $editorIds): self
    {
        $this->editorIds = $editorIds;
        return $this;
    }

    /**
     * Filter decisions taken in one or more reviewRoundIds
     *
     * @param int[]|null $reviewRoundIds The review round number, such as first or
     *   second round of reviews. NOT the unique review round id.
     */
    public function filterByReviewRoundIds(?array $reviewRoundIds): self
    {
        $this->reviewRoundIds = $reviewRoundIds;
        return $this;
    }

    /**
     * Filter decisions taken in one or more rounds
     *
     * @param int[]|null $rounds The review round number, such as first or
     *   second round of reviews. NOT the unique review round id.
     */
    public function filterByRounds(?array $rounds): self
    {
        $this->rounds = $rounds;
        return $this;
    }

    /**
     * Filter decisions taken in one or more workflow stages
     *
     * @param int[]|null $stageIds One or more WORKFLOW_STAGE_ID_ constants
     */
    public function filterByStageIds(?array $stageIds): self
    {
        $this->stageIds = $stageIds;
        return $this;
    }

    /**
     * Filter decisions taken for one or more submission ids
     *
     * @param int[]|null $submissionIds
     */
    public function filterBySubmissionIds(?array $submissionIds): self
    {
        $this->submissionIds = $submissionIds;
        return $this;
    }

    /**
     * @copydoc CollectorInterface::getQueryBuilder()
     */
    public function getQueryBuilder(): Builder
    {
        $qb = DB::table($this->dao->table)
            ->select([$this->dao->table . '.*'])
            ->when(!is_null($this->decisionTypes), function ($q) {
                $q->whereIn('decision', $this->decisionTypes);
            })
            ->when(!is_null($this->editorIds), function ($q) {
                $q->whereIn('editor_id', $this->editorIds);
            })
            ->when(!is_null($this->reviewRoundIds), function ($q) {
                $q->whereIn('review_round_id', $this->reviewRoundIds);
            })
            ->when(!is_null($this->rounds), function ($q) {
                $q->whereIn('round', $this->rounds);
            })
            ->when(!is_null($this->stageIds), function ($q) {
                $q->whereIn('stage_id', $this->stageIds);
            })
            ->when(!is_null($this->submissionIds), function ($q) {
                $q->whereIn('submission_id', $this->submissionIds);
            })
            ->orderBy('date_decided', 'asc');

        Hook::call('Decision::Collector', [&$qb, $this]);

        return $qb;
    }
}
