<?php

namespace UniEngine\Engine\Includes\Helpers\Users;

function getUsersTechLevel($techID, $user) {
    global $_Vars_GameElements;

    $userTechKey = $_Vars_GameElements[$techID];

    return $user[$userTechKey];
}

function getUsersEngineSpeedTechModifier($engineTechID, $user) {
    global $_Vars_TechSpeedModifiers;

    $engineTechSpeedModifier = $_Vars_TechSpeedModifiers[$engineTechID];
    $userTechLevel = getUsersTechLevel($engineTechID, $user);

    return (1 + ($engineTechSpeedModifier * $userTechLevel));
}

function getMaxStructuresQueueLength(&$user) {
    return (
        isPro($user) ?
        MAX_BUILDING_QUEUE_SIZE_PRO :
        MAX_BUILDING_QUEUE_SIZE
    );
}

?>
