import {TranslationStrings} from "./strings";
import {Fetch} from "../../v2/fetch";
import {TranslatableAPI} from "../index";

export type HeroSkill = {
    id: number,
    title: string,
    description: string,
    bullets: string[],
    icon: string,
    level: number
    sort: number
    group: string,
    value: number,
    locked: boolean,
}

export type SkillState = {
    skills: HeroSkill[],
    hxp: number
    hxp_needed: number
}

export class HxpManagementApi extends TranslatableAPI<TranslationStrings> {
    constructor() { super( 'user/soul/skills/hxp/pack' ) }

    public skills(): Promise<SkillState> {
        return this.fetch.from('/list')
            .request().get() as Promise<SkillState>;
    }

    public sell_skills(skills: number[]): Promise<boolean> {
        return this.fetch.from('/', {sell: skills.join(',')})
            .request().delete().then(s => s.success ?? false) as Promise<boolean>;
    }
}