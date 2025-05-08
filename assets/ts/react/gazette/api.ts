import {Fetch} from "../../v2/fetch";
import { TranslationStrings } from './strings';

export interface GazetteCitizen {
	name?: string;
	user?: {
		id: number;
	},
	causeOfDeath?:{
		label: string;
	},
	soul_visit?: string;
	town_visit?: string;
}

export type CouncilItem = [
	string,
	{
		id: number;
		name: string;
	}
]

export interface Gazette {
	name: string;
	day: number;
	text: string;
	textClass: string;
	signature: string;

	attack: number;
	defense: number;
	invasion: number;
	deaths: number;
	terror: number;

	death_inside: GazetteCitizen[];
	death_outside: GazetteCitizen[];

	reactorExplosion: boolean;
	devast: boolean;
	door: boolean;
	waterlost: number;
	windDirection?: number;
	wind: string;

	council: CouncilItem[];
}

export class GazetteAPI {

	private fetch: Fetch;

	constructor() {
		this.fetch = new Fetch('game/raventimes');
	}

	public get(day: number): Promise<Gazette> {
		return this.fetch.from('/gazette/'+day)
			.request().withCache().get() as Promise<Gazette>;
	}

}