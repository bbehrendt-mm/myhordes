import * as React from "react";
import {useEffect, useRef, useState} from "react";
import {Global} from "../../defaults";
import {BaseMounter} from "../index";
import {PollAnswerPayload, PollCreatorAPI} from "./api";
import {ResponseIndex} from "./strings";
import {randomUUIDv4} from "../../shims";

declare var $: Global;

interface MountProps {}

export class HordesPollCreator extends BaseMounter<MountProps> {
    protected render(_props: MountProps): React.ReactNode {
        return <PollCreatorWrapper />;
    }
}

type AnswerEntry = {
    id: string;
    title: Record<string, string>;
    desc: Record<string, string>;
    rawTitle: Record<string, string>;
    rawDesc: Record<string, string>;
    dependsOnId: string | null;
};

const isDescendantOf = (candidateId: string, targetId: string, allAnswers: AnswerEntry[]): boolean => {
    let currentId: string | null = candidateId;
    const visited = new Set<string>();
    while (currentId) {
        if (currentId === targetId) return true;
        if (visited.has(currentId)) break;
        visited.add(currentId);
        const parent = allAnswers.find(a => a.id === currentId);
        currentId = parent?.dependsOnId ?? null;
    }
    return false;
};

const PollCreatorWrapper = () => {
    const apiRef = useRef<PollCreatorAPI>(new PollCreatorAPI());
    const [data, setData] = useState<ResponseIndex | null>(null);
    const [loading, setLoading] = useState<boolean>(true);

    useEffect(() => {
        apiRef.current.index()
            .then(res => {
                setData(res);
                setLoading(false);
            })
            .catch(err => {
                console.error("Failed to load poll data:", err);
                setLoading(false);
            });
    }, []);

    if (loading || !data) {
        return <div className="loading"></div>;
    }

    return <PollCreatorForm data={data} api={apiRef.current} />;
};

const PollCreatorForm = ({data, api}: {data: ResponseIndex; api: PollCreatorAPI}) => {
    const strings = data.strings;

    const [selectedLang, setSelectedLang] = useState<string>(data.userLang ?? "multi");
    const [title, setTitle] = useState<Record<string, string>>({});
    const [startDate, setStartDate] = useState<string>(data.defaults.startDate);
    const [endDate, setEndDate] = useState<string>(data.defaults.endDate);
    const [premature, setPremature] = useState<boolean>(false);
    const [prematureStart, setPrematureStart] = useState<string>(data.defaults.startDate);
    const [multiple, setMultiple] = useState<boolean>(false);
    const [preview, setPreview] = useState<Record<string, string>>({});
    const [desc, setDesc] = useState<Record<string, string>>({});

    const [answers, setAnswers] = useState<AnswerEntry[]>([]);
    const [newAnswerTitle, setNewAnswerTitle] = useState<Record<string, string>>({});
    const [newAnswerDesc, setNewAnswerDesc] = useState<Record<string, string>>({});
    const [newAnswerDependsOn, setNewAnswerDependsOn] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);

    const resolveEmote = (s: string): [string | null, string] => {
        switch (s) {
            case ':)': s = ':smile:'; break;
            case ':d': s = ':lol:'; break;
            case ':(': s = ':sad:'; break;
            case ';)': s = ':blink:'; break;
            case ':o': s = ':surprise:'; break;
        }
        if (s.indexOf('"') > -1) return [null, s];
        return [data.emotes[s] ?? null, s];
    };

    const parseTwino = (text: string): string => {
        if (!text) return '';
        try {
            const autoLinks = $.client.config.autoParseLinks.get();
            return $.html.twinoParser.parseToString(text, resolveEmote, {autoLinks});
        } catch (e) {
            console.error(e);
        }
        return text;
    };

    const getMissingLangs = (values: Record<string, string>): string[] => {
        return data.langsCodes.filter(lang => !values[lang] || values[lang].trim().length === 0);
    };

    const checkField = (values: Record<string, string>, fieldName: string): boolean => {
        const missing = getMissingLangs(values);
        if (missing.length > 0) {
            const msg = strings.missing_fields
                .replace('{field}', fieldName)
                .replace('{langs}', missing.join(', '));

            $.html.error(msg);
            return false;
        }
        return true;
    };

    const handleAddAnswer = () => {
        if (!checkField(newAnswerTitle, strings.answer_title)) {
            return;
        }

        const answerTitleHtml: Record<string, string> = {};
        const answerDescHtml: Record<string, string> = {};
        const answerTitleRaw: Record<string, string> = {};
        const answerDescRaw: Record<string, string> = {};

        data.langsCodes.forEach(lang => {
            const rawT = (newAnswerTitle[lang] ?? '').trim();
            const rawD = (newAnswerDesc[lang] ?? '').trim();
            answerTitleRaw[lang] = rawT;
            answerDescRaw[lang] = rawD;
            answerTitleHtml[lang] = parseTwino(rawT);
            answerDescHtml[lang] = parseTwino(rawD);
        });

        const newId = randomUUIDv4();

        setAnswers(prev => [
            ...prev,
            {
                id: newId,
                title: answerTitleHtml,
                desc: answerDescHtml,
                rawTitle: answerTitleRaw,
                rawDesc: answerDescRaw,
                dependsOnId: newAnswerDependsOn,
            }
        ]);

        setNewAnswerTitle({});
        setNewAnswerDesc({});
        setNewAnswerDependsOn(null);
    };

    const moveUp = (index: number) => {
        if (index === 0) return;
        setAnswers(prev => {
            const next = [...prev];
            const temp = next[index - 1];
            next[index - 1] = next[index];
            next[index] = temp;
            return next;
        });
    };

    const moveDown = (index: number) => {
        if (index >= answers.length - 1) return;
        setAnswers(prev => {
            const next = [...prev];
            const temp = next[index + 1];
            next[index + 1] = next[index];
            next[index] = temp;
            return next;
        });
    };

    const copyAnswer = (answer: AnswerEntry) => {
        setNewAnswerTitle({...answer.rawTitle});
        setNewAnswerDesc({...answer.rawDesc});
        setNewAnswerDependsOn(answer.dependsOnId);
    };

    const deleteAnswer = (index: number) => {
        const deletedId = answers[index]?.id;
        setAnswers(prev =>
            prev
                .filter((_, i) => i !== index)
                .map(a => (a.dependsOnId === deletedId ? {...a, dependsOnId: null} : a))
        );
    };

    const handleSetDependency = (index: number, parentId: string | null) => {
        setAnswers(prev => {
            const next = [...prev];
            const target = next[index];
            if (!target) return prev;
            if (parentId === target.id) return prev;
            if (parentId && isDescendantOf(parentId, target.id, prev)) return prev;
            next[index] = {
                ...target,
                dependsOnId: parentId,
            };
            return next;
        });
    };

    const handleSubmitPoll = () => {
        if (!window.confirm(strings.confirm)) {
            return;
        }

        if (!checkField(title, strings.poll_title)) return;
        if (!checkField(preview, strings.short_description)) return;
        if (!checkField(desc, strings.poll_description)) return;

        if (answers.length < 2) {
            $.html.error(strings.min_answers_error);
            return;
        }

        const titleHtml: Record<string, string> = {};
        const previewHtml: Record<string, string> = {};
        const descHtml: Record<string, string> = {};

        data.langsCodes.forEach(lang => {
            titleHtml[lang] = parseTwino(title[lang] ?? '');
            previewHtml[lang] = parseTwino(preview[lang] ?? '');
            descHtml[lang] = parseTwino(desc[lang] ?? '');
        });

        const payloadAnswers: PollAnswerPayload[] = answers.map((a, idx) => {
            let dependsOnIndex: number | null = null;
            if (a.dependsOnId) {
                const parentIdx = answers.findIndex(other => other.id === a.dependsOnId);
                if (parentIdx !== -1 && parentIdx !== idx) {
                    dependsOnIndex = parentIdx;
                }
            }

            return {
                title: a.title,
                desc: a.desc,
                depends_on: dependsOnIndex,
            };
        });

        setIsSubmitting(true);
        api.createPoll({
            title: titleHtml,
            preview: previewHtml,
            desc: descHtml,
            start: startDate,
            end: endDate,
            premature,
            date_premature: prematureStart,
            multiple,
            answers: payloadAnswers,
        })
            .then(res => {
                setIsSubmitting(false);
                $.html.message('notice', strings.success_notice);
                $.ajax.load(null, res.url);
            })
            .catch(err => {
                setIsSubmitting(false);
                console.error("Failed to create poll:", err);
            });
    };

    return (
        <div>
            <h1 className="page-head">{strings.create_new_poll}</h1>

            <h5>{strings.editor_settings}</h5>
            <div className="row">
                <div className="padded cell rw-3 rw-md-4 rw-sm-12">
                    <div className="note note-lightest">
                        <label>
                            {strings.current_edit_lang}{' '}
                            <a className="help-button">
                                <div className="tooltip help">{strings.help_edit_lang}</div>
                                {strings.help}
                            </a>
                        </label>
                    </div>
                </div>
                <div className="padded cell rw-9 rw-md-8 rw-sm-12">
                    <div className="row-flex wrap gap">
                        {['multi', ...data.langsCodes].map(lang => (
                            <label className="cell" key={lang}>
                                <img alt={lang} src={data.flags[lang]} />
                                <input
                                    type="radio"
                                    name="fields_lang"
                                    value={lang}
                                    checked={selectedLang === lang}
                                    onChange={() => setSelectedLang(lang)}
                                />
                            </label>
                        ))}
                    </div>
                </div>
            </div>

            <h5>{strings.poll_data}</h5>

            {/* Poll Title */}
            <PollField
                description={strings.poll_title}
                langsCodes={data.langsCodes}
                flags={data.flags}
                selectedLang={selectedLang}
                values={title}
                onChange={(lang, val) => setTitle(prev => ({...prev, [lang]: val}))}
                type="text"
            />

            {/* Poll Start Date */}
            <div className="row">
                <div className="padded cell rw-3 rw-md-4 rw-sm-12">
                    <div className="note note-lightest">
                        <label htmlFor="poll_start">{strings.poll_start}</label>
                    </div>
                </div>
                <div className="padded cell rw-9 rw-md-8 rw-sm-12">
                    <input
                        id="poll_start"
                        name="poll_start"
                        type="date"
                        value={startDate}
                        min={data.defaults.startDate}
                        onChange={e => setStartDate(e.target.value)}
                    />
                </div>
            </div>

            {/* Poll End Date */}
            <div className="row">
                <div className="padded cell rw-3 rw-md-4 rw-sm-12">
                    <div className="note note-lightest">
                        <label htmlFor="poll_end">{strings.poll_end}</label>
                    </div>
                </div>
                <div className="padded cell rw-9 rw-md-8 rw-sm-12">
                    <input
                        id="poll_end"
                        name="poll_end"
                        type="date"
                        value={endDate}
                        min={data.defaults.minEndDate}
                        onChange={e => setEndDate(e.target.value)}
                    />
                </div>
            </div>

            {/* Premature results checkbox */}
            <div className="row">
                <div className="padded cell rw-3 rw-md-4 rw-sm-12">
                    <div className="note note-lightest">
                        <label htmlFor="poll_premature">{strings.poll_premature}</label>
                    </div>
                </div>
                <div className="padded cell rw-9 rw-md-8 rw-sm-12">
                    <input
                        id="poll_premature"
                        name="poll_premature"
                        type="checkbox"
                        checked={premature}
                        onChange={e => setPremature(e.target.checked)}
                    />
                </div>
            </div>

            {/* Premature results start date */}
            <div className="row">
                <div className="padded cell rw-3 rw-md-4 rw-sm-12">
                    <div className="note note-lightest">
                        <label htmlFor="poll_premature_start">{strings.poll_premature_start}</label>
                    </div>
                </div>
                <div className="padded cell rw-9 rw-md-8 rw-sm-12">
                    <input
                        id="poll_premature_start"
                        name="poll_premature_start"
                        type="date"
                        value={prematureStart}
                        min={data.defaults.startDate}
                        disabled={!premature}
                        onChange={e => setPrematureStart(e.target.value)}
                    />
                </div>
            </div>

            {/* Multiple choice checkbox */}
            <div className="row">
                <div className="padded cell rw-3 rw-md-4 rw-sm-12">
                    <div className="note note-lightest">
                        <label htmlFor="poll_multi">{strings.poll_multi}</label>
                    </div>
                </div>
                <div className="padded cell rw-9 rw-md-8 rw-sm-12">
                    <input
                        id="poll_multi"
                        name="poll_multi"
                        type="checkbox"
                        checked={multiple}
                        onChange={e => setMultiple(e.target.checked)}
                    />
                </div>
            </div>

            {/* Preview (Kurzbeschreibung) */}
            <PollField
                description={strings.short_description}
                langsCodes={data.langsCodes}
                flags={data.flags}
                selectedLang={selectedLang}
                values={preview}
                onChange={(lang, val) => setPreview(prev => ({...prev, [lang]: val}))}
                type="textarea"
                small={true}
            />

            {/* Description (Beschreibung der Umfrage) */}
            <PollField
                description={strings.poll_description}
                langsCodes={data.langsCodes}
                flags={data.flags}
                selectedLang={selectedLang}
                values={desc}
                onChange={(lang, val) => setDesc(prev => ({...prev, [lang]: val}))}
                type="textarea"
                small={false}
            />

            <h5>{strings.answers}</h5>
            <div className="row-table" id="answers-table">
                {answers.map((answer, index) => {
                    const displayLang = selectedLang === 'multi'
                        ? (data.userLang in answer.rawTitle ? data.userLang : data.langsCodes[0])
                        : selectedLang;
                    const displayText = answer.rawTitle[displayLang] ?? answer.title[displayLang] ?? '';
                    const parentAnswer = answer.dependsOnId
                        ? answers.find(a => a.id === answer.dependsOnId)
                        : null;
                    const parentIdx = parentAnswer ? answers.indexOf(parentAnswer) : -1;

                    return (
                        <div className="row" key={answer.id}>
                            <div className="padded cell rw-6 rw-lg-5 rw-sm-12">
                                <div style={{ display: 'flex', alignItems: 'center', gap: '6px', paddingLeft: parentAnswer ? '16px' : '0' }}>
                                    {parentAnswer && (
                                        <span style={{ opacity: 0.6, fontSize: '1.1em' }} title={`${strings.depends_on}: #${parentIdx + 1}`}>
                                            ↳
                                        </span>
                                    )}
                                    <span>
                                        <b>#{index + 1}.</b> {displayText}
                                    </span>
                                </div>
                            </div>
                            <div className="padded cell rw-4 rw-lg-4 rw-sm-6">
                                <select
                                    className="inline full-width"
                                    value={answer.dependsOnId ?? ''}
                                    onChange={e => handleSetDependency(index, e.target.value ? e.target.value : null)}
                                    title={strings.depends_on}
                                >
                                    <option value="">{strings.no_dependency}</option>
                                    {answers.map((other, otherIdx) => {
                                        if (other.id === answer.id) return null;
                                        if (isDescendantOf(other.id, answer.id, answers)) return null;
                                        const otherText = other.rawTitle[displayLang] ?? other.title[displayLang] ?? '';
                                        return (
                                            <option key={other.id} value={other.id}>
                                                {strings.depends_on}: #{otherIdx + 1} ({otherText.length > 25 ? otherText.substring(0, 25) + '…' : otherText})
                                            </option>
                                        );
                                    })}
                                </select>
                            </div>
                            <div className="padded cell rw-2 rw-lg-3 rw-sm-6 right">
                                <button className="small inline" type="button" onClick={() => moveUp(index)}>
                                    <img alt="^" src={data.icons.arrowUp} />
                                </button>
                                <button className="small inline" type="button" onClick={() => moveDown(index)}>
                                    <img alt="V" src={data.icons.arrowDown} />
                                </button>
                                <button className="small inline" type="button" onClick={() => copyAnswer(answer)}>
                                    <i className="fa fa-copy"></i>
                                </button>
                                <button className="small inline" type="button" onClick={() => deleteAnswer(index)}>
                                    <img alt="" src={data.icons.delete} />
                                </button>
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* New Answer inputs */}
            <PollField
                description={strings.answer_title}
                langsCodes={data.langsCodes}
                flags={data.flags}
                selectedLang={selectedLang}
                values={newAnswerTitle}
                onChange={(lang, val) => setNewAnswerTitle(prev => ({...prev, [lang]: val}))}
                type="text"
            />
            <PollField
                description={strings.answer_desc}
                langsCodes={data.langsCodes}
                flags={data.flags}
                selectedLang={selectedLang}
                values={newAnswerDesc}
                onChange={(lang, val) => setNewAnswerDesc(prev => ({...prev, [lang]: val}))}
                type="textarea"
                small={true}
            />
            <div className="row">
                <div className="padded cell rw-3 rw-md-4 rw-sm-12">
                    <div className="note note-lightest">
                        <label htmlFor="new_answer_depends">{strings.depends_on}</label>
                    </div>
                </div>
                <div className="padded cell rw-9 rw-md-8 rw-sm-12">
                    <select
                        id="new_answer_depends"
                        className="full-width"
                        value={newAnswerDependsOn ?? ''}
                        onChange={e => setNewAnswerDependsOn(e.target.value ? e.target.value : null)}
                    >
                        <option value="">{strings.no_dependency}</option>
                        {answers.map((a, i) => {
                            const displayLang = selectedLang === 'multi'
                                ? (data.userLang in a.rawTitle ? data.userLang : data.langsCodes[0])
                                : selectedLang;
                            const aText = a.rawTitle[displayLang] ?? a.title[displayLang] ?? '';
                            return (
                                <option key={a.id} value={a.id}>
                                    #{i + 1}: {aText.length > 40 ? aText.substring(0, 40) + '…' : aText}
                                </option>
                            );
                        })}
                    </select>
                </div>
            </div>
            <div className="row">
                <div className="cell rw-4 ro-8 rw-md-6 ro-md-8 rw-sm-12 ro-sm-0">
                    <button id="add_answer" type="button" onClick={handleAddAnswer}>
                        {strings.add_answer}
                    </button>
                </div>
            </div>

            <h5>{strings.finish_editing}</h5>
            <div className="row">
                <div className="cell rw-4 ro-8 rw-md-6 ro-md-8 rw-sm-12 ro-sm-0">
                    <button
                        id="submit_poll"
                        type="button"
                        disabled={isSubmitting}
                        onClick={handleSubmitPoll}
                    >
                        {strings.submit_poll}
                    </button>
                </div>
            </div>
        </div>
    );
};

const PollField = (props: {
    description: string;
    langsCodes: string[];
    flags: Record<string, string>;
    selectedLang: string;
    values: Record<string, string>;
    onChange: (lang: string, val: string) => void;
    type: 'text' | 'textarea';
    small?: boolean;
}) => {
    return (
        <div className="row">
            <div className="padded cell rw-3 rw-md-4 rw-sm-12">
                <div className="note note-lightest">
                    <label>{props.description}</label>
                </div>
            </div>
            <div className="padded cell rw-9 rw-md-8 rw-sm-12">
                {props.langsCodes.map(lang => {
                    const isVisible = props.selectedLang === 'multi' || props.selectedLang === lang;
                    return (
                        <div
                            key={lang}
                            className={`row-flex gap ${props.type !== 'textarea' ? 'v-center' : ''} ${!isVisible ? 'hidden' : ''}`}
                        >
                            <div className="cell shrink-0 grow-0">
                                <label>
                                    <img alt={lang} src={props.flags[lang]} />
                                </label>
                            </div>
                            <div className="cell grow-1">
                                {props.type === 'textarea' ? (
                                    <textarea
                                        className="full-width"
                                        style={props.small ? {minHeight: '80px', height: '80px'} : undefined}
                                        value={props.values[lang] ?? ''}
                                        onChange={e => props.onChange(lang, e.target.value)}
                                    />
                                ) : (
                                    <input
                                        type="text"
                                        className="full-width"
                                        value={props.values[lang] ?? ''}
                                        onChange={e => props.onChange(lang, e.target.value)}
                                    />
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
};
