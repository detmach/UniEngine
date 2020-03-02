<?php

use UniEngine\Engine\Modules\Development;
use UniEngine\Engine\Modules\Development\Components\LegacyQueue;
use UniEngine\Engine\Includes\Helpers\Planets;
use UniEngine\Engine\Includes\Helpers\World\Elements;
use UniEngine\Engine\Includes\Helpers\World\Resources;

function BatimentBuildingPage(&$CurrentPlanet, $CurrentUser)
{
    global $_EnginePath, $_Lang, $_Vars_GameElements, $_Vars_ElementCategories,
           $_SkinPath, $_GameConfig, $_GET, $_Vars_PremiumBuildingPrices, $_Vars_MaxElementLevel, $_Vars_PremiumBuildings;

    $BuildingPage = '';

    includeLang('worldElements.detailed');

    CheckPlanetUsedFields ($CurrentPlanet);

    $Now = time();
    $SubTemplate = gettemplate('buildings_builds_row');

    PlanetResourceUpdate($CurrentUser, $CurrentPlanet, $Now);

    // Handle Commands
    Development\Input\UserCommands\handleStructureCommand(
        $CurrentUser,
        $CurrentPlanet,
        $_GET,
        [
            "timestamp" => $Now
        ]
    );
    // End of - Handle Commands

    $buildingsQueue = Planets\Queues\Structures\parseQueueString($CurrentPlanet['buildQueue']);
    $buildingsQueueLength = count($buildingsQueue);

    if($buildingsQueueLength < ((isPro($CurrentUser)) ? MAX_BUILDING_QUEUE_SIZE_PRO : MAX_BUILDING_QUEUE_SIZE ))
    {
        $CanBuildElement = true;
    }
    else
    {
        $CanBuildElement = false;
    }

    $queueComponent = LegacyQueue\render([
        'queue' => $buildingsQueue,
        'currentTimestamp' => $Now,

        'getQueueElementCancellationLinkHref' => function ($queueElement) {
            $queueElementIdx = $queueElement['queueElementIdx'];
            $listID = $queueElement['listID'];
            $isFirstQueueElement = ($queueElementIdx === 0);
            $cmd = ($isFirstQueueElement ? "cancel" : "remove");

            return buildHref([
                'path' => 'buildings.php',
                'query' => [
                    'cmd' => $cmd,
                    'listid' => $listID
                ]
            ]);
        }
    ]);

    $queueStateDetails = Development\Utils\getQueueStateDetails([
        'queue' => [
            'type' => Development\Utils\QueueType::Planetary,
            'content' => $buildingsQueue,
        ],
        'user' => $CurrentUser,
        'planet' => $CurrentPlanet,
    ]);
    $planetFieldsUsageCounter = 0;

    foreach ($queueStateDetails['queuedResourcesToUse'] as $resourceKey => $resourceValue) {
        if (Resources\isPlanetaryResource($resourceKey)) {
            $CurrentPlanet[$resourceKey] -= $resourceValue;
        } else if (Resources\isUserResource($resourceKey)) {
            $CurrentUser[$resourceKey] -= $resourceValue;
        }
    }
    foreach ($queueStateDetails['queuedElementLevelModifiers'] as $elementID => $elementLevelModifier) {
        $elementKey = Elements\getElementKey($elementID);
        $CurrentPlanet[$elementKey] += $elementLevelModifier;
        $planetFieldsUsageCounter += $elementLevelModifier;
    }

    foreach($_Vars_ElementCategories['build'] as $Element)
    {
        if(in_array($Element, $_Vars_ElementCategories['buildOn'][$CurrentPlanet['planet_type']]))
        {
            if(($CurrentPlanet['field_current'] + $planetFieldsUsageCounter) < CalculateMaxPlanetFields($CurrentPlanet)) {
                $RoomIsOk = true;
            } else {
                $RoomIsOk = false;
            }

            $parse = array();
            $blockReason = [];

            $parse['click'] = '';
            $NextBuildLevel = $CurrentPlanet[$_Vars_GameElements[$Element]] + 1;
            $skip = false;

            if(IsTechnologieAccessible($CurrentUser, $CurrentPlanet, $Element))
            {
                $HaveRessources = IsElementBuyable($CurrentUser, $CurrentPlanet, $Element, false);

                if($Element == 31)
                {
                    // Block Lab Upgrade is Research running (and Config dont allow that)
                    if($CurrentUser['techQueue_Planet'] > 0 AND $CurrentUser['techQueue_EndTime'] > 0 AND $_GameConfig['BuildLabWhileRun'] != 1)
                    {
                        $blockReason[] = $_Lang['in_working'];
                    }
                }
                if(!empty($_Vars_MaxElementLevel[$Element]))
                {
                    if($NextBuildLevel > $_Vars_MaxElementLevel[$Element])
                    {
                        $blockReason[] = $_Lang['onlyOneLevel'];
                        $skip = true;
                    }
                }

                if(isset($_Vars_PremiumBuildings[$Element]) && $_Vars_PremiumBuildings[$Element] == 1)
                {
                    if (
                        $CurrentUser['darkEnergy'] < $_Vars_PremiumBuildingPrices[$Element] &&
                        $skip == false
                    ) {
                        $blockReason[] = $_Lang['BuildFirstLevel'];
                    }
                }

                if(isOnVacation($CurrentUser))
                {
                    $blockReason[] = $_Lang['ListBox_Disallow_VacationMode'];
                }

                if($parse['click'] != '')
                {
                    // Don't do anything here
                }
                else if($RoomIsOk AND $CanBuildElement)
                {
                    if($buildingsQueueLength == 0)
                    {
                        if($NextBuildLevel == 1)
                        {
                            if($HaveRessources == true)
                            {
                                $parse['click'] = "<a href=\"?cmd=insert&building={$Element}\" class=\"lime\">{$_Lang['BuildFirstLevel']}</a>";
                            }
                            else
                            {
                                $blockReason[] = $_Lang['BuildFirstLevel'];
                            }
                        }
                        else
                        {
                            if($HaveRessources == true)
                            {
                                $parse['click'] = "<a href=\"?cmd=insert&building={$Element}\" class=\"lime\">{$_Lang['BuildNextLevel']} {$NextBuildLevel}</a>";
                            }
                            else
                            {
                                $blockReason[] = "{$_Lang['BuildNextLevel']} {$NextBuildLevel}";
                            }
                        }
                    }
                    else
                    {
                        if($HaveRessources == true)
                        {
                            $ThisColor = 'lime';
                        }
                        else
                        {
                            $ThisColor = 'orange';
                        }

                        $parse['click'] = "<a href=\"?cmd=insert&building={$Element}\" class=\"{$ThisColor}\">{$_Lang['InBuildQueue']}<br/>({$_Lang['level']} {$NextBuildLevel})</a>";
                    }
                }
                else if($RoomIsOk AND !$CanBuildElement)
                {
                    $blockReason[] = $_Lang['QueueIsFull'];
                }
                else
                {
                    if($CurrentPlanet['planet_type'] == 3)
                    {
                        $blockReason[] = $_Lang['NoMoreSpace_Moon'];
                    }
                    else
                    {
                        $blockReason[] = $_Lang['NoMoreSpace'];
                    }
                }
            }

            // $BuildingPage .= parsetemplate($SubTemplate, $parse);

            $elementQueuedLevel = Elements\getElementState($Element, $CurrentPlanet, $CurrentUser)['level'];
            $isElementInQueue = isset(
                $queueStateDetails['queuedElementLevelModifiers'][$Element]
            );
            $elementQueueLevelModifier = (
                $isElementInQueue ?
                $queueStateDetails['queuedElementLevelModifiers'][$Element] :
                0
            );
            $elementCurrentLevel = (
                $elementQueuedLevel +
                ($elementQueueLevelModifier * -1)
            );

            $listElement = Development\Components\ListViewElementRow\render([
                'elementID' => $Element,
                'user' => $CurrentUser,
                'planet' => $CurrentPlanet,
                'timestamp' => $Now,
                'elementDetails' => [
                    'currentState' => $elementCurrentLevel,
                    'isInQueue' => $isElementInQueue,
                    'queueLevelModifier' => $elementQueueLevelModifier,
                    'hasTechnologyRequirementMet' => IsTechnologieAccessible($CurrentUser, $CurrentPlanet, $Element),
                    'isUpgradeAvailableNow' => false,
                    'isUpgradeQueueableNow' => false,
                    'whyUpgradeImpossible' => [ end($blockReason) ],
                ],
                'getUpgradeElementActionLinkHref' => function () use ($Element) {
                    return "?cmd=insert&amp;building={$Element}";
                },
            ]);

            $BuildingPage .= $listElement['componentHTML'];
        }
    }

    foreach ($queueStateDetails['queuedResourcesToUse'] as $resourceKey => $resourceValue) {
        if (Resources\isPlanetaryResource($resourceKey)) {
            $CurrentPlanet[$resourceKey] += $resourceValue;
        } else if (Resources\isUserResource($resourceKey)) {
            $CurrentUser[$resourceKey] += $resourceValue;
        }
    }
    foreach ($queueStateDetails['queuedElementLevelModifiers'] as $elementID => $elementLevelModifier) {
        $elementKey = Elements\getElementKey($elementID);
        $CurrentPlanet[$elementKey] -= $elementLevelModifier;
    }

    $parse = $_Lang;

    $parse['planet_field_current'] = $CurrentPlanet['field_current'];
    $parse['planet_field_max'] = CalculateMaxPlanetFields($CurrentPlanet);
    $parse['field_libre'] = $parse['planet_field_max'] - $CurrentPlanet['field_current'];

    $parse['BuildList'] = $queueComponent['componentHTML'];
    $parse['BuildingsList'] = $BuildingPage;

    display(parsetemplate(gettemplate('buildings_builds'), $parse), $_Lang['Builds']);
}

?>
