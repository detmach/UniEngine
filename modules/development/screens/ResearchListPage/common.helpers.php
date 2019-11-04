<?php

namespace UniEngine\Engine\Modules\Development\Screens\ResearchListPage\Helpers;

use UniEngine\Engine\Includes\Helpers\Common;
use UniEngine\Engine\Includes\Helpers\Users;
use UniEngine\Engine\Includes\Helpers\Planets;


function getResearchNetworkStatus ($user) {
    $researchLabElementID = 31;
    $researchLabElementKey = _getElementPlanetKey($researchLabElementID);

    $userID = $user['id'];
    $userResearchNetworkSize = Users\getResearchNetworkSize($user);

    $query_GetOtherLabs = '';
    $query_GetOtherLabs .= "SELECT `id`, `buildQueue`, `{$researchLabElementKey}` ";
    $query_GetOtherLabs .= "FROM {{table}} ";
    $query_GetOtherLabs .= "WHERE `id_owner` = {$userID} AND `planet_type` = 1;";

    $dbResult_GetOtherLabs = doquery($query_GetOtherLabs, 'planets');

    if ($dbResult_GetOtherLabs->num_rows === 0) {
        return [
            'connectedLabs' => 0,
            'connectedLabsLevel' => 0,
            'totalLabsLevel' => 0,
            'labsCount' => 0,
            'planetsWithLabInStructuresQueue' => []
        ];
    }

    $labsLevels = [];
    $planetsWithLabInStructuresQueue = [];

    while ($labRowData = $dbResult_GetOtherLabs->fetch_assoc()) {
        $thisPlanetID = $labRowData['id'];
        $thisResearchLabLevel = $labRowData[$researchLabElementKey];

        $labsLevels[] = $thisResearchLabLevel;

        if (empty($labRowData['buildQueue'])) {
            continue;
        }

        $hasResearchLabInQueue = Planets\Queues\Structures\hasElementInQueue(
            $labRowData,
            $researchLabElementID
        );

        if (!$hasResearchLabInQueue) {
            continue;
        }

        $planetsWithLabInStructuresQueue[] = $thisPlanetID;
    }

    $labsLevels = array_filter(
        $labsLevels,
        function ($value) {
            return ($value > 0);
        }
    );

    rsort($labsLevels);

    $connectedLabsLevels = Common\Collections\firstN($labsLevels, $userResearchNetworkSize);

    return [
        'allLabsCount' => count($labsLevels),
        'allLabsLevel' => array_sum($labsLevels),
        'connectedLabsCount' => count($connectedLabsLevels),
        'connectedLabsLevel' => array_sum($connectedLabsLevels),
        'planetsWithLabInStructuresQueue' => $planetsWithLabInStructuresQueue
    ];
}

//  Arguments:
//      - $user (&Object)
//      - $params (Object)
//          - planetsWithLabInStructuresQueueIDs (Array<String>)
//          - currentTimestamp (Number)
//
//  Returns: Object
//      - planetsWithUnfinishedLabUpgrades (Array<Object>)
//
function updatePlanetsWithLabsInQueue (&$user, $params) {
    global $_EnginePath;

    $planetsWithLabInQueueIDs = $params['planetsWithLabInQueueIDs'];
    $currentTimestamp = $params['currentTimestamp'];

    include($_EnginePath . '/includes/functions/CheckLabInQueue.php');

    $planetsWithLabInQueueIDsString = implode(', ', $planetsWithLabInQueueIDs);

    $query_GetPlanetsWithLabInQueue = "";
    $query_GetPlanetsWithLabInQueue .= "SELECT * ";
    $query_GetPlanetsWithLabInQueue .= "FROM {{table}} ";
    $query_GetPlanetsWithLabInQueue .= "WHERE `id` IN ({$planetsWithLabInQueueIDsString}) ";
    $query_GetPlanetsWithLabInQueue .= "LIMIT 1";
    $query_GetPlanetsWithLabInQueue .= ";";

    $dbResult_GetPlanetsWithLabInQueue = doquery($query_GetPlanetsWithLabInQueue, "planets");

    $planetsToUpdate = [];
    $planetsWithUnfinishedLabUpgrades = [];

    while ($dbResultRow_PlanetWithLabInQueue = $dbResult_GetPlanetsWithLabInQueue->fetch_assoc()) {
        $lastLabUpgradeEndTimestamp = CheckLabInQueue($dbResultRow_PlanetWithLabInQueue);

        if (
            $lastLabUpgradeEndTimestamp === false ||
            $lastLabUpgradeEndTimestamp > $currentTimestamp
        ) {
            $planetsWithUnfinishedLabUpgrades[] = $dbResultRow_PlanetWithLabInQueue;

            continue;
        }

        $hasPlanetBeenUpdated = HandlePlanetQueue(
            $dbResultRow_PlanetWithLabInQueue,
            $user,
            $currentTimestamp,
            true
        );

        if ($hasPlanetBeenUpdated) {
            $planetsToUpdate[] = $dbResultRow_PlanetWithLabInQueue;
        }
    }

    if (!empty($planetsToUpdate)) {
        HandlePlanetUpdate_MultiUpdate($planetsToUpdate, $user);
    }

    return [
        'planetsWithUnfinishedLabUpgrades' => $planetsWithUnfinishedLabUpgrades
    ];
}

?>
