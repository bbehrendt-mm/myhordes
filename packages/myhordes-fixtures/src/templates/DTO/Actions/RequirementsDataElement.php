<?php

namespace MyHordes\Fixtures\DTO\Actions;

use App\Entity\AwardPrototype;
use App\Entity\PictoPrototype;
use App\Enum\SortDefinitionWord;
use Doctrine\ORM\EntityManagerInterface;
use MyHordes\Fixtures\DTO\Element;

/**
 * @property string identifier
 * @method self identifier(string $v)
 * @property RequirementsAtom[] atomList
 * @property int type
 * @method self type(int $v)
 * @property string text
 * @method self text(string $v)
 * @property string text_key
 * @method self text_key(string $v)
 * @property array collection
 * @method self collection(array $v)
 *
 * @method RequirementsDataContainer commit()
 * @method RequirementsDataContainer discard()
 */
class RequirementsDataElement extends Element {
    public function add(RequirementsAtom $atom): self {
        $this->atomList = array_merge($this->atomList ?? [], [$atom]);
        $this->sortAtomList();
        return $this;
    }

    public function clear( string $class ): self {
        $this->atomList = array_values( array_filter( $this->atomList, fn(RequirementsAtom $a) => !is_a( $a->getClass(), $class, true ) ) );
        return $this;
    }

    private function sortAtomList(): void {
        $tmp = $this->atomList;
        $references = array_unique( array_filter( array_map( fn(RequirementsAtom $a) => $a->sort->word->stable() ? $a->getClass() : null, $tmp ) ) );

        usort( $tmp, fn(RequirementsAtom $a, RequirementsAtom $b) => $a->sort->priority <=> $b->sort->priority );

        $default = array_reverse( array_filter( $tmp, fn(RequirementsAtom $a) => $a->sort->word === SortDefinitionWord::Default ) );

        $start = array_reverse( array_filter( $tmp, fn(RequirementsAtom $a) => $a->sort->word === SortDefinitionWord::Start ) );
        $end = array_values( array_filter( $tmp, fn(RequirementsAtom $a) => $a->sort->word === SortDefinitionWord::End ) );

        /** @var RequirementsAtom[] $unstable_tmp */
        $unstable_tmp = array_merge(
            array_reverse( array_filter( $tmp, fn(RequirementsAtom $a) => $a->sort->word === SortDefinitionWord::Before ) ),
            array_values( array_filter( $tmp, fn(RequirementsAtom $a) => $a->sort->word === SortDefinitionWord::After ) )
        );

        $this->atomList = array_merge( $start, $default, $end );

        $unstable = [];
        foreach ($unstable_tmp as $unstable_element) {
            if (!array_key_exists( $unstable_element->getClass(), $unstable )) $unstable[$unstable_element->getClass()] = [];
            $unstable[$unstable_element->getClass()][] = $unstable_element;
        }

        while (!empty($unstable)) {
            $changed = false;

            foreach ($unstable as $ref => $unstable_tmp)
                if (in_array( $ref, $references )) {

                    $hitting = array_filter( $this->atomList, fn(RequirementsAtom $a) => $a->getClass() === $ref );

                    $first = array_key_first( $hitting );
                    $last = array_key_first( $hitting );

                    $before = array_values( array_filter( $unstable_tmp, fn(RequirementsAtom $a) => $a->sort->word === SortDefinitionWord::Before ) );
                    $after  = array_values( array_filter( $unstable_tmp, fn(RequirementsAtom $a) => $a->sort->word === SortDefinitionWord::After ) );

                    $block_a = array_slice( $this->atomList, 0, $first - 1 );
                    $block_b = array_slice( $this->atomList, $first, ($last - $first) + 1 );
                    $block_c = array_slice( $this->atomList, $last + 1 );

                    $this->atomList = array_merge( $block_a, $before, $block_b, $after, $block_c );
                    $references = array_unique( array_filter( array_map( fn(RequirementsAtom $a) => $a->getClass(), $this->atomList ) ) );

                    $changed = true;
                    unset( $unstable[$ref] );
                    break;
                }

            if ($changed)
                $references = array_unique( array_map( fn(RequirementsAtom $a) => $a->getClass(), $this->atomList ) );
            else {
                $before = array_values( array_filter( $unstable, fn(RequirementsAtom $a) => $a->sort->word === SortDefinitionWord::Before ) );
                $after  = array_values( array_filter( $unstable, fn(RequirementsAtom $a) => $a->sort->word === SortDefinitionWord::After ) );
                $this->atomList = array_merge( $before, $this->atomList, $after );
                $unstable = [];
            }

        }

    }

    /**
     * @param RequirementsAtom[] $v
     * @return self
     */
    public function atomList(array $v): self {
        $this->atomList = $v;
        $this->sortAtomList();
        return $this;
    }

    public function beforeSerialization(): void
    {
        parent::afterSerialization();
        $this->sortAtomList();
    }

    public function afterSerialization(): void
    {
        parent::afterSerialization();
        $this->sortAtomList();
    }
}