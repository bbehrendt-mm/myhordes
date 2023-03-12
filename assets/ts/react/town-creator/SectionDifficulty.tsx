import * as React from "react";

import {Global} from "../../defaults";
import {ResponseTownList, TownRules} from "./api";
import {useContext, useEffect, useRef} from "react";
import {Globals} from "./Wrapper";
import {OptionCoreTemplate, OptionFreeText, OptionSelect, OptionToggleMulti} from "./Input";
import {number} from "prop-types";
import {AtLeast} from "./Permissions";

declare var $: Global;

export const TownCreatorSectionDifficulty = () => {
    const globals = useContext(Globals)

    const difficulty = globals.strings.difficulty;

    enum Direction { north, south, west, east };
	const getOppositeDir = (direction: Direction) => {
		return direction + ((direction % 2) === 1 ? -1 : 1);
	};
    const handleCustomMarginChange = (input: HTMLInputElement, direction: Direction) => {
        const direction_opposite = getOppositeDir(direction);

        const margin = parseInt(input.value);
        const margin_opposite = parseInt(globals.getOption( 'rules.margin_custom.'+Direction[direction_opposite]) ?? 25);

        let input_opposite = document.getElementsByName('margin_custom_'+Direction[direction_opposite])[0] as HTMLInputElement;

        const max_value = 100 - margin_opposite;
        input.max = max_value.toString();
        input_opposite.max = (100 - margin).toString();

        globals.setOption('rules.margin_custom.'+Direction[direction], margin);
    }

    return <div data-map-property="rules">
        <h5>{ difficulty.section }</h5>

        { /* Well Level */ }
        <OptionCoreTemplate propName="well" propHelp={difficulty.well_help} propTitle={difficulty.well}>
            <select name="wellPreset" value={globals.getOption( 'rules.wellPreset' ) ?? ''} onChange={globals.setOption}>
                { difficulty.well_presets.map( option => <React.Fragment key={option.value}>
                    <option value={option.value}>{ option.label }</option>
                </React.Fragment> ) }
            </select>
            { globals.getOption( 'rules.wellPreset' ) === '_fixed' && (
                <div className="row-flex">
                    <div className="padded cell">
                        <input  type="number" min={0} max={300} value={globals.getOption( 'rules.well.min' ) ?? 120} onChange={e => {
                            const v =  parseInt((e.target as HTMLInputElement).value);
                            globals.setOption('rules.well.min', v);
                            globals.setOption('rules.well.max', v);
                        }}/>
                    </div>
                </div>
            ) }
            { globals.getOption( 'rules.wellPreset' ) === '_range' && (
                <div className="row-flex" data-map-property="well">
                    <div className="padded cell grow-1"><input type="number" data-prop-name="min" min={0} max={Math.min((globals.getOption( 'rules.well.max' ) as number) ?? 300, 300)} value={globals.getOption( 'rules.well.min' ) ?? 90} onChange={globals.setOption}/></div>
                    <div className="padded cell shrink-1">-</div>
                    <div className="padded cell grow-1"><input className="padded cell grow-1" type="number" data-prop-name="max" min={Math.max((globals.getOption( 'rules.well.min' ) as number) ?? 0, 0)} max={300} value={globals.getOption( 'rules.well.max' ) ?? 180} onChange={globals.setOption}/></div>
                </div>
            ) }
        </OptionCoreTemplate>

        { /* Map Settings */ }
        <OptionSelect value={ globals.getOption( 'rules.mapPreset' ) } propName="mapPreset" propTitle={ difficulty.map }
                      options={ difficulty.map_presets.filter(globals.elevation < 3 ? v=> ['small','normal'].includes(v.value) : ()=>true).map( m => ({ value: m.value, title: m.label }) ) }
        />
        { globals.getOption( 'rules.mapPreset' ) === '_custom' && (
            <AtLeast elevation="crow">
                <OptionFreeText type="number" value={ globals.getOption( 'rules.map.min' ) as string ?? '26' } propName="map"
                                inputArgs={{min: 10, max: 35}} propTitle={ difficulty.map_exact }
                                onChange={e => {
                                    const v =  parseInt((e.target as HTMLInputElement).value);
                                    globals.setOption('rules.map.min', v);
                                    globals.setOption('rules.map.max', v);
                                }}

                />
                <OptionFreeText type="number" value={ globals.getOption( 'rules.ruins' ) as string ?? '20' } propName="ruins"
                                inputArgs={{min: 0, max: 30}} propTitle={ difficulty.map_ruins }
                />
                <OptionFreeText type="number" value={ globals.getOption( 'rules.explorable_ruins' ) as string ?? '1' } propName="explorable_ruins"
                                inputArgs={{min: 0, max: 3}} propTitle={ difficulty.map_e_ruins }
                />
            </AtLeast>
        ) }

        { /* Position Settings */ }
        <AtLeast elevation="crow">
            <OptionSelect value={ globals.getOption( 'rules.mapMarginPreset' ) ?? 'normal' } propName="mapMarginPreset" propTitle={ difficulty.position }
                          options={ difficulty.position_presets.map( m => ({ value: m.value, title: m.label }) ) }
            />
        </AtLeast>
        { globals.getOption( 'rules.mapMarginPreset' ) === '_custom' && (
            <AtLeast elevation="crow">
				{
					Object.keys(Direction).map((dir_str) => {
						const dir = Direction[dir_str];
						if(typeof dir !== 'number') return;
						
						return (
							<OptionFreeText 
								type="number"
								value={ globals.getOption( `rules.margin_custom.${dir_str}` ) as string ?? '25' }
								propName={`margin_custom_${dir_str}`}
								inputArgs={{min: 0, max: 100}}
								propTitle={ difficulty[`position_${dir_str}`] }
								onChange={e => handleCustomMarginChange(e.target as HTMLInputElement, dir as Direction)}
							/>
						);
					})
				}
            </AtLeast>
        ) }

        { /* Attack Settings */ }
        <OptionSelect value={ globals.getOption( 'rules.features.attacks' ) } propName="features.attacks" propTitle={ difficulty.attacks }
                      options={ difficulty.attacks_presets.map( m => ({ value: m.value, title: m.label }) ) }
        />

    </div>;
};