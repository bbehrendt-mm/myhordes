<?php

namespace App\Entity;

use App\Repository\CouncilEntryTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OrderBy;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;

#[ORM\Entity(repositoryClass: CouncilEntryTemplateRepository::class)]
#[Table]
#[UniqueConstraint(name: 'council_entry_template_name_unique', columns: ['name'])]
class CouncilEntryTemplate
{
    const int CouncilBranchModeNone = 0;
    const int CouncilBranchModeStructured = 1;
    const int CouncilBranchModeRandom = 2;
    const int CouncilNodeContextOnly                   =    0;
    const int CouncilRootNodeGenericMCIntro                =  990001;
    const int CouncilRootNodeGenericIntroFew               =  990005;
    const int CouncilNodeGenericMCIntro                =    1;
    const int CouncilNodeGenericIntroFew               =    5;
    const int CouncilNodeGenericFollowUpAny            =   20;
    const int CouncilNodeGenericBeginVoteAny           =   30;
    const int CouncilNodeGenericVoteAny                =   40;
    const int CouncilNodeGenericVoteResponseAny        =   45;
    const int CouncilNodeGenericEndVoteAny             =   50;
    const int CouncilNodeGenericEndVoteResponseA       =   55;
    const int CouncilNodeGenericEndVoteResponseB       =   56;
    const int CouncilNodeGenericStrawAny               =   60;
    const int CouncilNodeGenericStrawFew               =   65;
    const int CouncilNodeGenericStrawInitAny           =   65;
    const int CouncilNodeGenericStrawResponseAny       =   70;
    const int CouncilNodeGenericStrawFinalAny          =   80;
    const int CouncilNodeGenericStrawResultResponseAny =  100;
    const int CouncilNodeRootShamanFirst                   = 991001;
    const int CouncilNodeRootShamanNext                    = 991002;
    const int CouncilNodeRootShamanSingle                  = 991003;
    const int CouncilNodeRootShamanNone                    = 991004;
    const int CouncilNodeRootShamanFew                     = 991005;
    const int CouncilRootNodeShamanIntroFirst              = 991011;
    const int CouncilRootNodeShamanIntroNext               = 991012;
    const int CouncilRootNodeShamanIntroSingle             = 991013;
    const int CouncilRootNodeShamanIntroNone               = 991014;
    const int CouncilRootNodeShamanIntroFew                = 991015;
    const int CouncilRootNodeShamanIntroFew2               = 991016;
    const int CouncilRootNodeShamanFollowUpFirst           = 991021;
    const int CouncilRootNodeShamanFollowUpNext            = 991022;
    const int CouncilRootNodeShamanBeginVoteAny            = 991030;
    const int CouncilRootNodeShamanVoteAny                 = 991040;
    const int CouncilRootNodeShamanEndVoteAny              = 991050;
    const int CouncilRootNodeShamanStrawAny                = 991060;
    const int CouncilRootNodeShamanStrawFew                = 991065;
    const int CouncilRootNodeShamanStrawResponseAny        = 991070;
    const int CouncilRootNodeShamanStrawFinalAny           = 991080;
    const int CouncilRootNodeShamanStrawResultAny          = 991090;
    const int CouncilRootNodeShamanStrawResultResponseAny  = 991100;
    const int CouncilRootNodeShamanFinalAny                = 991110;
    const int CouncilNodeShamanIntroFirst              = 1011;
    const int CouncilNodeShamanIntroNext               = 1012;
    const int CouncilNodeShamanIntroSingle             = 1013;
    const int CouncilNodeShamanIntroNone               = 1014;
    const int CouncilNodeShamanIntroFew                = 1015;
    const int CouncilNodeShamanFollowUpAny             = 1020;
    const int CouncilNodeShamanFollowUpFirst           = 1021;
    const int CouncilNodeShamanFollowUpNext            = 1022;
    const int CouncilNodeShamanBeginVoteAny            = 1030;
    const int CouncilNodeShamanVoteAny                 = 1040;
    const int CouncilNodeShamanVoteResponseAny         = 1045;
    const int CouncilNodeShamanEndVoteAny              = 1050;
    const int CouncilNodeShamanEndVoteResponseA        = 1055;
    const int CouncilNodeShamanEndVoteResponseB        = 1056;
    const int CouncilNodeShamanStrawAny                = 1060;
    const int CouncilNodeShamanStrawFew                = 1065;
    const int CouncilNodeShamanStrawFinalAny           = 1070;
    const int CouncilNodeShamanStrawResponseAny        = 1080;
    const int CouncilNodeShamanStrawResultAny          = 1090;
    const int CouncilNodeShamanStrawResultResponseAny  = 1100;
    const int CouncilNodeShamanFinalAny                = 1110;
    const int CouncilNodeRootGuideFirst                   = 992001;
    const int CouncilNodeRootGuideNext                    = 992002;
    const int CouncilNodeRootGuideSingle                  = 992003;
    const int CouncilNodeRootGuideNone                    = 992004;
    const int CouncilNodeRootGuideFew                     = 992005;
    const int CouncilRootNodeGuideIntroFirst              = 992011;
    const int CouncilRootNodeGuideIntroNext               = 992012;
    const int CouncilRootNodeGuideIntroSingle             = 992013;
    const int CouncilRootNodeGuideIntroNone               = 992014;
    const int CouncilRootNodeGuideIntroFew                = 992015;
    const int CouncilRootNodeGuideIntroFew2                = 992016;
    const int CouncilRootNodeGuideFollowUpFirst           = 992021;
    const int CouncilRootNodeGuideFollowUpNext            = 992022;
    const int CouncilRootNodeGuideBeginVoteAny            = 992030;
    const int CouncilRootNodeGuideVoteAny                 = 992040;
    const int CouncilRootNodeGuideEndVoteAny              = 992050;
    const int CouncilRootNodeGuideStrawAny                = 992060;
    const int CouncilRootNodeGuideStrawFew                = 992065;
    const int CouncilRootNodeGuideStrawResponseAny        = 992070;
    const int CouncilRootNodeGuideStrawFinalAny           = 992080;
    const int CouncilRootNodeGuideStrawResultAny          = 992090;
    const int CouncilRootNodeGuideStrawResultResponseAny  = 992100;
    const int CouncilRootNodeGuideFinalAny                = 992110;
    const int CouncilNodeGuideIntroFirst              = 2011;
    const int CouncilNodeGuideIntroNext               = 2012;
    const int CouncilNodeGuideIntroSingle             = 2013;
    const int CouncilNodeGuideIntroNone               = 2014;
    const int CouncilNodeGuideIntroFew                = 2015;
    const int CouncilNodeGuideFollowUpAny             = 2020;
    const int CouncilNodeGuideFollowUpFirst           = 2021;
    const int CouncilNodeGuideFollowUpNext            = 2022;
    const int CouncilNodeGuideBeginVoteAny            = 2030;
    const int CouncilNodeGuideVoteAny                 = 2040;
    const int CouncilNodeGuideVoteResponseAny         = 2045;
    const int CouncilNodeGuideEndVoteAny              = 2050;
    const int CouncilNodeGuideEndVoteResponseA        = 2055;
    const int CouncilNodeGuideEndVoteResponseB        = 2056;
    const int CouncilNodeGuideStrawAny                = 2060;
    const int CouncilNodeGuideStrawFew                = 2065;
    const int CouncilNodeGuideStrawFinalAny           = 2070;
    const int CouncilNodeGuideStrawResponseAny        = 2080;
    const int CouncilNodeGuideStrawResultAny          = 2090;
    const int CouncilNodeGuideStrawResultResponseAny  = 2100;
    const int CouncilNodeGuideFinalAny                = 2110;
    const int CouncilNodeRootCataFirst                   = 993001;
    const int CouncilNodeRootCataNext                    = 993002;
    const int CouncilNodeRootCataSingle                  = 993003;
    const int CouncilNodeRootCataNone                    = 993004;
    const int CouncilNodeRootCataFew                     = 993005;
    const int CouncilRootNodeCataIntroFirst              = 993011;
    const int CouncilRootNodeCataIntroNext               = 993012;
    const int CouncilRootNodeCataIntroSingle             = 993013;
    const int CouncilRootNodeCataIntroNone               = 993014;
    const int CouncilRootNodeCataIntroFew                = 993015;
    const int CouncilRootNodeCataIntroFew2               = 993016;
    const int CouncilRootNodeCataFollowUpFirst           = 993021;
    const int CouncilRootNodeCataFollowUpNext            = 993022;
    const int CouncilRootNodeCataBeginVoteAny            = 993030;
    const int CouncilRootNodeCataVoteAny                 = 993040;
    const int CouncilRootNodeCataEndVoteAny              = 993050;
    const int CouncilRootNodeCataStrawAny                = 993060;
    const int CouncilRootNodeCataStrawFew                = 993065;
    const int CouncilRootNodeCataStrawResponseAny        = 993070;
    const int CouncilRootNodeCataStrawFinalAny           = 993080;
    const int CouncilRootNodeCataStrawResultAny          = 993090;
    const int CouncilRootNodeCataStrawResultResponseAny  = 993100;
    const int CouncilRootNodeCataFinalAny                = 993110;
    const int CouncilNodeCataIntroFirst              = 3011;
    const int CouncilNodeCataIntroNext               = 3012;
    const int CouncilNodeCataIntroSingle             = 3013;
    const int CouncilNodeCataIntroNone               = 3014;
    const int CouncilNodeCataIntroFew                = 3015;
    const int CouncilNodeCataFollowUpAny             = 3020;
    const int CouncilNodeCataFollowUpFirst           = 3021;
    const int CouncilNodeCataFollowUpNext            = 3022;
    const int CouncilNodeCataBeginVoteAny            = 3030;
    const int CouncilNodeCataVoteAny                 = 3040;
    const int CouncilNodeCataVoteResponseAny         = 3045;
    const int CouncilNodeCataEndVoteAny              = 3050;
    const int CouncilNodeCataEndVoteResponseA        = 3055;
    const int CouncilNodeCataEndVoteResponseB        = 3056;
    const int CouncilNodeCataStrawAny                = 3060;
    const int CouncilNodeCataStrawFew                = 3065;
    const int CouncilNodeCataStrawFinalAny           = 3070;
    const int CouncilNodeCataStrawResponseAny        = 3080;
    const int CouncilNodeCataStrawResultAny          = 3090;
    const int CouncilNodeCataStrawResultResponseAny  = 3100;
    const int CouncilNodeCataFinalAny                = 3110;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;
    #[ORM\Column(type: 'string', length: 64)]
    private $name;
    #[ORM\Column(type: 'text', nullable: true)]
    private $text;
    #[ORM\Column(type: 'integer', nullable: true)]
    private $semantic;
    #[ORM\Column(type: 'array', nullable: true)]
    private $variableTypes = [];
    #[ORM\Column(type: 'boolean')]
    private $vocal;
    #[ORM\ManyToMany(targetEntity: CouncilEntryTemplate::class)]
    #[OrderBy(['semantic' => 'ASC'])]
    private $branches;
    #[ORM\Column(type: 'integer')]
    private $branchMode;
    #[ORM\Column(type: 'integer')]
    private $branchSizeMin;
    #[ORM\Column(type: 'integer')]
    private $branchSizeMax;
    #[ORM\Column(type: 'json')]
    private $variableDefinitions = [];
    public function __construct()
    {
        $this->branches = new ArrayCollection();
    }
    public function __toString()
    {
        return "_CouncilEntryTemplate_{$this->getName()}";
    }
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getName(): ?string
    {
        return $this->name;
    }
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
    public function getText(): ?string
    {
        return $this->text;
    }
    public function setText(?string $text): self
    {
        $this->text = $text;

        return $this;
    }
    public function getSemantic(): ?int
    {
        return $this->semantic;
    }
    public function setSemantic(?int $semantic): self
    {
        $this->semantic = $semantic;

        return $this;
    }
    public function getVariableTypes(): ?array
    {
        return $this->variableTypes;
    }
    public function setVariableTypes(array $variableTypes): self
    {
        $this->variableTypes = $variableTypes;

        return $this;
    }
    public function getVocal(): ?bool
    {
        return $this->vocal;
    }
    public function setVocal(bool $vocal): self
    {
        $this->vocal = $vocal;

        return $this;
    }
    /**
     * @return Collection|self[]
     */
    public function getBranches(): Collection
    {
        return $this->branches;
    }
    public function addBranch(self $branch): self
    {
        if (!$this->branches->contains($branch)) {
            $this->branches[] = $branch;
        }

        return $this;
    }
    public function removeBranch(self $branch): self
    {
        $this->branches->removeElement($branch);

        return $this;
    }
    public function getBranchMode(): ?int
    {
        return $this->branchMode;
    }
    public function setBranchMode(int $branchMode): self
    {
        $this->branchMode = $branchMode;

        return $this;
    }
    public function getBranchSizeMin(): ?int
    {
        return $this->branchSizeMin;
    }
    public function setBranchSizeMin(int $branchSizeMin): self
    {
        $this->branchSizeMin = $branchSizeMin;

        return $this;
    }
    public function getBranchSizeMax(): ?int
    {
        return $this->branchSizeMax;
    }
    public function setBranchSizeMax(int $branchSizeMax): self
    {
        $this->branchSizeMax = $branchSizeMax;

        return $this;
    }
    public function getVariableDefinitions(): ?array
    {
        return $this->variableDefinitions;
    }
    public function setVariableDefinitions(array $variableDefinitions): self
    {
        $this->variableDefinitions = $variableDefinitions;

        return $this;
    }
}
