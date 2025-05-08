import * as React from "react";
import {useState} from "react";
import {GazetteAPI, Gazette} from "./api";
import {TranslationStrings} from "./strings";
import {BaseMounter} from "../index";
import { Tooltip } from '../tooltip/Wrapper';

const ZoneDirectionConstants = {
    DirectionNorthWest: 1,
    DirectionNorth: 2,
    DirectionNorthEast: 3,
    DirectionWest: 4,
    DirectionEast: 6,
    DirectionSouthWest: 7,
    DirectionSouth: 8,
    DirectionSouthEast: 9,
};

const getwind_directionText = (directionValue: number, strings: TranslationStrings): string => {
    if (!strings) return '';
    const map: Record<string, string> = {
        [ZoneDirectionConstants.DirectionNorthWest]: strings.dir_nw,
        [ZoneDirectionConstants.DirectionNorth]: strings.dir_n,
        [ZoneDirectionConstants.DirectionNorthEast]: strings.dir_ne,
        [ZoneDirectionConstants.DirectionWest]: strings.dir_w,
        [ZoneDirectionConstants.DirectionEast]: strings.dir_e,
        [ZoneDirectionConstants.DirectionSouthWest]: strings.dir_sw,
        [ZoneDirectionConstants.DirectionSouth]: strings.dir_s,
        [ZoneDirectionConstants.DirectionSouthEast]: strings.dir_se,
    };
    return map[directionValue] || '';
};


type GazetteStore = {[key: string]: Gazette};
type GazetteUpdateDay = (day: number) => void;
const windowStore = (window as unknown as {
	GazetteStore?: GazetteStore;
	GazetteSetDay?: GazetteUpdateDay[];
	GazetteSetDays: (day: number) => void;
});

const getStore = (): GazetteStore => {
	if (!windowStore.GazetteStore) {
		windowStore.GazetteStore = {};
	}
	console.log('getStore', windowStore.GazetteStore);
	return windowStore.GazetteStore;
}

interface mountProps {
    initial: Gazette,
	strings: TranslationStrings,
	soul: boolean,
}

export class HordesGazette extends BaseMounter<mountProps>{
    protected render(props: mountProps): React.ReactNode {
        return <HordesGazetteWrapper {...props} />;
    }
}

const HordesGazetteWrapper = (props: mountProps) => {

    const api = new GazetteAPI();
	const store = getStore();
	const strings = props.strings;
	const [gazette, setGazette] = useState<Gazette>( props.initial );	

    const getWindDirStr = () => getwind_directionText(gazette.windDirection, strings);

	const tally = {
		final: gazette.day % 5,
		repeat: Math.floor(gazette.day / 5),
	};

	React.useEffect(() => {
		if (!windowStore.GazetteSetDay) {
			windowStore.GazetteSetDay = [];
			windowStore.GazetteSetDays = (day: number) => {
				windowStore.GazetteSetDay.forEach(fn => fn(day));
			}
		}
		
		windowStore.GazetteSetDay.push((day: number) => {
			if (store[day]) {
				setGazette(store[day]);
				return;
			}
			
			api.get(day).then((newGazette: Gazette) => {
				setGazette(newGazette);
				store[day] = newGazette;
			});
		});
	}, []);

    return <>
		<div id="gazette">
			<div id="newspage-front" className="newspage">
				<div id="gazette-headline">
					{ gazette.name } ({ gazette.day })
				</div>
				<div id="gazette-content" className={gazette.textClass}>
					<span dangerouslySetInnerHTML={{ __html: gazette.text }}></span>
					<div id="gazette-signature">
						- {strings.signature}
					</div>
				</div>
				{ gazette.day > 1 && (<>
					<div id="gazette-deaths">
						<div id="gazette-death-inside" className="death">
							<div className="death-category">
								{strings.dead_in_town} { gazette.death_inside.length > 0 && `(${gazette.death_inside.length})` }
							</div>
							{ gazette.death_inside.length === 0 ? (<>
								<Tooltip additionalClasses={['tooltip', 'normal']}>
									{gazette.reactorExplosion ? strings.no_dead_in_town?.with_reactor : strings.no_dead_in_town?.without_reactor}
								</Tooltip>
								<span>{strings.no_dead_in_town?.no_deaths}</span>
							</>) : (<>
								{ gazette.death_inside.map((citizen, i) =>
									<span key={`death-in-${i}`}>
										<span x-ajax-href={ citizen.soul_visit ? citizen.soul_visit : citizen.town_visit } style={{cursor: 'pointer', textDecoration: 'underline'}}>{citizen.name}</span>{i !== gazette.death_inside.length - 1 ? ', ' : ''}
										<Tooltip additionalClasses={['tooltip', 'normal']}>
											<h1>{strings.cause_of_death}</h1>
											{citizen.causeOfDeath?.label}
										</Tooltip>
									</span>
								)}
							</>)}
						</div>
						{ gazette.death_outside && gazette.death_outside.length > 0 && (
							<div id="gazette-death-outside" className="death">
								<div className="death-category">
									{strings.other_victims} ({ gazette.death_outside.length }):
								</div>
								{ gazette.death_outside.map((citizen, i) =>
									<span key={`death-out-${i}`}>
										<span x-ajax-href={ props.soul ? citizen.soul_visit : citizen.town_visit } style={{cursor: 'pointer', textDecoration: 'underline'}}>{citizen.name}</span>{i !== gazette.death_outside.length - 1 ? ', ' : ''}
										<Tooltip additionalClasses={['tooltip', 'normal']}>
											<h1>{strings.cause_of_death}</h1>
											{citizen.causeOfDeath?.label}
										</Tooltip>
									</span>
								)}
							</div>
						)}
					</div>
					<div id="gazette-tally">
						{ tally.repeat > 0 && (
							Array.from({ length: tally.repeat }, (_, k) => k + 1).map(i => {
								const v = (i % 3) + 1;
								return <div key={`tally-repeat-${i}`} className={`tally tally-5-${v}`}></div>;
							})
						)}
						{ tally.final > 0 && (
							<div className={`tally tally-${tally.final}`}></div>
						)}
						<div className="tooltip normal">
							{(strings.that_makes_n_days).replace('{day}', gazette.day.toString())}
						</div>
					</div>
				</>)}
			</div>
			<div id="newspage-back" className="newspage">
				{ gazette.day > 1 ? (<>
					<div id="gazette-attack" className={`row ${gazette.devast ? 'devast' : ''} ${gazette.door ? 'opened' : 'closed'}`.trim()}>
						<div className="nightstat nightstat-attack"><span className="count">{ gazette.attack }</span>
							{' '+strings.zombies}</div>
						<div className="nightstat nightstat-defense">
							{ !gazette.door ? (<>
								<span className="count">{ gazette.defense }</span>
								{' '+strings.defense}
							</>) : (
								<span className="invasion">{strings.gate_is_open}</span>
							)}
							{ gazette.invasion > 0 && (
								<span className="invasion">{ gazette.invasion } {strings.zombies_have_invaded}</span>
							)}
						</div>
						<div className="nightstat nightstat-deaths">
							{ gazette.deaths > 0 ? (<>
								<span className="count">{ gazette.deaths }</span> {strings.victims}
							</>) : (
								<span className="count">{strings.no_victims}</span>
							)}
							{ gazette.terror > 0 && (<>
								<br /><span className="terror">{ gazette.terror } {strings.paralyzed_by_fear}</span>
							</>)}
						</div>
					</div>
					{ (gazette.windDirection !== 0 || gazette.waterlost > 0) && (
						<div id="buildingdetails" className="row">
							{ gazette.windDirection !== 0 && (
								<div id="wind">
                                    <a className="help-button">
                                        <div className="tooltip help" dangerouslySetInnerHTML={{ __html: (strings.wind_help_tooltip).replace('{sector}', getWindDirStr()) }}></div>
                                        {strings.help}
                                    </a> <span dangerouslySetInnerHTML={{ __html: gazette.wind }}></span>
								</div>
							)}
							{ gazette.waterlost > 0 && (
								<div id="waterlost">
									{(strings.lost_well_water).replace('{count}', gazette.waterlost.toString())}
								</div>
							)}
						</div>
					)}
				</>) : (
					<div id="gazette-empty"></div>
				)}
			</div>
		</div>
		<div id="gazette-switcher"><label className="button" htmlFor="gazette-switch">{strings.turn_over}</label></div>
		{ gazette.council && gazette.council.length > 0 && (
			<>
				<h1 className="page-head">{strings.town_council}</h1>
				<div className="center">
					<div id="council">
						<img className="council-big hide-md hide-sm" alt="" src={gazette.council.length < 30 ? "/build/images/background/council_white.gif" : "/build/images/background/council_white_big.gif"} />
						<img className="council-small hide-desktop hide-lg" alt="" src="/build/images/background/council_white.gif" />
						<div>
							{ gazette.council.map((council_item, index) => (
								<div key={`council-${index}`} className={!council_item[1] ? 'status' : ''}>
									{ council_item[1] && <span className={`author color-${council_item[1].id % 10}`}>{council_item[1].name}</span>}
									{' '}
									<span dangerouslySetInnerHTML={{ __html: council_item[0] }}></span>
								</div>
							))}
						</div>
					</div>
				</div>
			</>
		)}
	</>
};
