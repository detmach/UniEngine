<tr class="queueinv">
    <td colspan="2">&nbsp;</td>
</tr>
<tr>
    <th
        class="pad2 w20x"
        rowspan="2"
    >
        {ElementNo}
    </th>
    <th class="pad2">
        <a href="infos.php?gid={ElementID}">
            {Name}
        </a>
        ({LevelText} {Level}, <b class="{ModeColor}">{ModeText}</b>)
        <br />
        <b
            class="lime endDate"
            title="<center>{EndTitleBeg} {EndDateExpand}<br/>{EndTitleHour} {EndTimeExpand}<br/>({InfoBox_BuildTime}: {BuildTime})</center>"
        >
            {EndDate}
        </b>
    </th>
</tr>
<tr>
    <th
        class="pad2"
        colspan="2"
    >
        <a href="buildings.php?listid={ListID}&amp;cmd=remove">
            {Lang_CancelBtn_Text}
        </a>
    </th>
</tr>
