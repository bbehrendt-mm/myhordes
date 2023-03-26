import * as React from "react";

import {Global} from "../../defaults";
import {ResponseTownList, TownRules} from "./api";
import {useContext, useEffect, useRef} from "react";
import {Globals} from "./Wrapper";
import {OptionCoreTemplate, OptionFreeText, OptionSelect, OptionToggleMulti} from "./Input";
import {number} from "prop-types";
import {AtLeast} from "./Permissions";

declare var $: Global;

export const TownCreatorSectionAnimator = () => {
    const globals = useContext(Globals)

    const animation = globals.strings.animation;

    return <div data-map-property="rules">
        <h5>{ animation.section }</h5>

        { /* Scheduler Settings */ }
        <AtLeast notForEvents={true}>
            <OptionFreeText propTitle={animation.schedule} type={ "datetime-local" } propHelp={animation.schedule_help}
                            value={ globals.getOption( 'head.townSchedule' ) } propName="head.townSchedule"
                            onChange={e => {
                                globals.setOption('head.townSchedule', (e.target as HTMLInputElement).value);
                                if ((globals.getOption( 'head.townIncarnation' ) ?? 'none') === 'incarnate')
                                    globals.setOption( 'head.townIncarnation', 'none' );
                            }}
            />
        </AtLeast>

        { /* SP Settings */ }
        <OptionSelect propTitle={animation.sp}
                      value={globals.getOption( 'rules.features.give_soulpoints' ) ? 'all' : 'none'} propName="features.give_soulpoints"
                      options={ animation.sp_presets.map( preset => ({ value: preset.value, title: preset.label, help: preset.help }) ) }
                      onChange={e => globals.setOption('rules.features.give_soulpoints', (e.target as HTMLInputElement).value === 'all')}
        />

        { /* Picto Settings */ }
        <OptionSelect propTitle={animation.pictos}
                      value={
                        globals.getOption( 'rules.features.enable_pictos' )
                            ? (globals.getOption( 'rules.features.give_all_pictos' ) ? 'all' : 'reduced')
                            : 'none'
                      } propName="features.pictos"
                      options={ animation.pictos_presets.map( preset => ({ value: preset.value, title: preset.label, help: preset.help }) ) }
                      onChange={e => {
                          const v = (e.target as HTMLInputElement).value;
                          globals.setOption('rules.features.enable_pictos', v !== 'none')
                          if (v !== 'none') globals.setOption('rules.features.give_all_pictos', v === 'all')
                      }}
        />

        { /* Picto Rule Settings */ }
        { globals.getOption( 'rules.features.enable_pictos' ) && (
            <OptionSelect propTitle={animation.picto_rules}
                          value={globals.getOption( 'rules.modifiers.strict_picto_distribution' ) ? 'small' : 'normal'} propName="modifiers.strict_picto_distribution"
                          options={ animation.picto_rules_presets.map( preset => ({ value: preset.value, title: preset.label, help: preset.help }) ) }
                          onChange={e => globals.setOption('rules.modifiers.strict_picto_distribution', (e.target as HTMLInputElement).value === 'small')}
            />
        ) }

        { /* Participation Settings */ }
        <AtLeast notForEvents={true}>
            <OptionSelect propTitle={animation.participation}
                          value={globals.getOption( 'head.townIncarnation' ) ?? 'none'} propName="<.head.townIncarnation"
                          options={ animation.participation_presets.map( preset => ({ value: preset.value, title: preset.label, help: preset.help }) ) }
                          onChange={e => {
                              const v = (e.target as HTMLSelectElement).value;
                              globals.setOption('head.townIncarnation', v);
                              if (v === 'incarnate') globals.removeOption( 'head.townSchedule' );
                          }}
            />
        </AtLeast>

        { /* Management Settings */ }
        <OptionToggleMulti propName="features.<" options={[
            { value: globals.getOption( 'rules.lock_door_until_full' ) as boolean, name: 'lock_door_until_full', title: animation.management.lock_door, help: animation.management.lock_door_help },
            { value: globals.getOption( 'rules.open_town_limit' ) as number === 2, name: 'open_town_limit', title: animation.management.negate, help: animation.management.negate_help, onChange: e => {
                globals.setOption('rules.open_town_limit', (e.target as HTMLInputElement).checked ? 2 : 7)
            }},
            { value: globals.getOption( 'head.townEventTag' ) as boolean, name: '<.head.townEventTag', title: animation.management.event_tag, help: animation.management.event_tag_help },
        ]} propTitle={animation.management.section}/>

    </div>;
};