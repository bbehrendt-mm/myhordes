import * as React from "react";
import {useRef} from "react";
import {Global} from "../../defaults";
import {BaseMounter} from "../index";
import {useSlider} from "../utils";

declare var $: Global;

interface forumProps {
    title: string,
    description: string,
    url: string,
    icon: string,
    sort: number,
    new: boolean,
}

interface mountProps {
    icon: string,
    title: string,
    collapse: boolean
    forums: forumProps[],
}

export class HordesForumGroup extends BaseMounter<mountProps> {
    protected render(props: mountProps): React.ReactNode {
        return <ForumGroup {...props} />;
    }
}

export class HordesForum extends BaseMounter<forumProps> {
    protected render(props: forumProps): React.ReactNode {
        return <Forum {...props} />;
    }
}

const ForumGroup = (props: mountProps) => {
    const groupRef = useRef<HTMLDivElement>();
    const [currentState, showElements, renderElements, setShowElements] = useSlider(
        !props.collapse,
        groupRef,
        '.content > .forum-preview',
        {duration: 100 * props.forums.length, delay: 100},
        (show, index, element) => {
            element.classList.add('animating');
            element.animate([
                { opacity: show ? 0.8 : 1 },
                { opacity: show ? 1 : 0.8 },
            ], {fill: "both", duration: 150, delay: show ? (100 * (1+index)) : (100 * (props.forums.length - index)), easing: "ease-in-out"},)
        },
        (show, index, element) => {
            element.classList.remove('animating');
        }
    )

    return <div className="forumGroup" ref={groupRef}>
        <div className={`header ${currentState ? 'open' : 'collapsed'}`} onClick={() => setShowElements(!showElements)}>
            <img alt="" src={ props.icon }/>
            <span>{ props.title }</span>
        </div>
        <div className="content">
            { renderElements && props.forums.sort( (a,b) => a.sort - b.sort ).map(
                forum => <React.Fragment key={ forum.url }>
                    <Forum {...forum} />
                </React.Fragment>
            ) }
        </div>
    </div>
}

const Forum = (props: forumProps) => {
    return <div
        className={`forum-preview ${props.new ? 'new' : ''} ${props.description ? 'forum-preview-desc' : ''}`}
        onMouseDown={ e => {
            if (e.button === 1) {
                e.preventDefault();
                window.open(props.url, '_blank');
            }
        } }
        onClick={ e => {
            $.ajax.load( null, props.url, true, {}, () => {} )
        }}
    >
        <img alt="" src={ props.icon }/>
        <div>
            <div>{ props.title }</div>
            { props.description && <span>{ props.description }</span> }
        </div>
    </div>
}