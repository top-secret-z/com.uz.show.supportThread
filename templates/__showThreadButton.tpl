{if $entry->supportThreadID && $supportThread->canRead()}
    <div class="box boxInfo">
        <div class="boxContent">
            <div class="formSubmit">
                <a href="{link application='wbb' controller='Thread' object=$supportThread}{/link}" class="button buttonPrimary">{lang}show.entry.button.jumpToSupportThread{/lang}<br /><small>{lang}show.entry.supportThread.info{/lang}</small></a>
            </div>
        </div>
    </div>
{/if}
