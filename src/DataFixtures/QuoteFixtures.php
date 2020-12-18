<?php

namespace App\DataFixtures;

use App\Entity\Quote;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class QuoteFixtures extends Fixture implements DependentFixtureInterface {

    private $entityManager;

    protected static $quotes_data = [
        // French Quotes
        ['name' => 'fr_001', 'content' => '... Carnage... Défaite... Trahison... Lacheté... Voilà le vocabulaire à employer.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_002', 'content' => 'Allez quoi, ouvrez ! Promis je le referai plus... Les gars ?...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_003', 'content' => 'Aujourd\'hui enfin, j\'ai compris...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_004', 'content' => 'Avant de bannir, apprends donc à courir...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_005', 'content' => 'Avez-vous déjà vu du cartilage se liquéfier ? On s\'en souvient longtemps.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_006', 'content' => 'Avoir un taudis, c\'est perdre ses amis', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_007', 'content' => 'Boire ou mourir ? Pourquoi choisir ?', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_008', 'content' => 'Ca fait déjà trois jours que j\'ai très mal au ventre... Je me gave de bicarbonate de soude, mais si c\'est une de ces saletés de parasites qui vous dévorent de l\'intérieur...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_009', 'content' => 'Ce que j\'aime avec les expéditions en équipe c\'est quand ils creusent et que je ramasse !', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_010', 'content' => 'Chaos du soir bien peu d\'espoir ?', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_011', 'content' => 'Chaos le matin, favorise le larcin !', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_012', 'content' => 'Contre cent zombies, la meilleure technique c\'est encore la fuite !', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_013', 'content' => 'Cultivez la pensée positive. Vous allez mourir. Dans tous les cas.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_014', 'content' => 'Dans les territoires extérieurs, il y a des ombres qui passent parfois à deux pas de nous. Des ombres colossales ! Et on ne les entend pas.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_015', 'content' => 'Depuis que cette vieille Rosie s\'est fait bouffer, plus besoin de faire le ménage ! ....Aaaaaahh ! Tante Rose ! ! ...Nooooon ! !... arrggll burrrrgl...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_016', 'content' => 'Des fois derrière le Héros se cache le crapaud', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_017', 'content' => 'Des fois, aux abords des relais, on trouve des ossements en remuant la terre. Mais on n\'arrive jamais à les identifier', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_018', 'content' => 'En expédition, quand tu sens le danger, attends et laisse-les tourner...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_019', 'content' => 'En ville, tout le monde l\'aimait, la doyenne. C\'est bien dommage qu\'on ait fini de la manger hier.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_020', 'content' => 'Garde toujours DEUX balles dans ton flingue. Une pour dégommer un macchabée, une pour toi si tu rates..', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_021', 'content' => 'Gifler son voisin, c\'est comme gifler un parpaing.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_022', 'content' => 'Hier avec Jessica on a décidé de tout partager. Aujourd\'hui je suis héros...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_023', 'content' => 'Hier, on était trois. Joe nous a dit : “plus on est nombreux, plus on est fort ! On va à gauche !”. On l\'a laissé tourner. Le lendemain, on a tout récupéré...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_024', 'content' => 'Il est dit : “Les Chroniques contiennent l\'essence de ces Terres, la Mémoire des oubliés”', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_025', 'content' => 'Il existe un seul moyen d\'oublier : ai-je besoin de vous en parler ?', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_026', 'content' => 'Il n\'y a pas de guerres de religion. Il n\'y a pas de guerre. Il n\'y a pas le temps.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_027', 'content' => 'Il s\'agissait juste de finir le travail... Vous vous posez sans doute la question... On l\'a effectivement fini... Mais à quel prix ?', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_028', 'content' => 'J\'ai pas fermé l\'oeil depuis trois jours, depuis que Carla est morte. Hein ? Ouais, je sais, c\'était la semaine dernière. Mais maintenant, elle cogne dans la cave.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_029', 'content' => 'J\'ai tellement faim que je boufferais mon voisin. Vu la vigueur des zombies d\'hier, ça peut pas être mauvais pour la santé.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_030', 'content' => 'J\'ai toujours eu une pensée émue pour les héros. Ils ont leur nom gravé à jamais sur la pierre.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_031', 'content' => 'J\'aime bien m\'évader, changer de peau, rêver. En général je fais tout ça une fois décédé...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_032', 'content' => 'Je sais, je sais. La bouffe est pas terrible mais au moins elle bouge pas quand tu croques dedans.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_033', 'content' => 'Jeremy m\'a dit : tu vas voir on va bien les avoir. Ils ont bien rigolé quand ils l\'ont pendu par les pieds...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_034', 'content' => 'Jerrycan quand j\'arrose les zombie.⇒ je ricane quand j\'arrose les zombie', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_035', 'content' => 'L\'âme se souvient. Souviens-t\'en !', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_036', 'content' => 'La liberté se trouve dans la pastille blanche...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_037', 'content' => 'Le mode chaos, c\'est la cerise sur le tombeau...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_038', 'content' => 'Le veilleur songe à l\'oeil qui lui manque.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_039', 'content' => 'Le vieux Chuck a croisé la Horde, une nuit, il y a longtemps. Et il en est revenu. Sur le coup il a serré les fesses. Depuis, il ne les a toujours pas desserrées.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_040', 'content' => 'Mon chien, y a pas à dire, je l\'adore ! Il me défend et me distrait. Et puis si j\'ai faim...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_041', 'content' => 'N\'oublie jamais le nom de celui qui a volé', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_042', 'content' => 'N\'oubliez pas : les amis ne le restent pas pour l\'éternité.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_043', 'content' => 'Ne vous engagez pas ! Ne venez jamais !', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_044', 'content' => 'On avait le choix entre lui pardonner ou la punir. On lui a finalement construit un cremato-cue. Pour ne plus avoir faim.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_045', 'content' => 'On essaie tous d\'imiter le grognement d\'un Zombie. Mais qui essaie d\'imiter le cri d\'un humain dont la chair, encore palpitante de vie, est dévorée ?', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_046', 'content' => 'On raconte beaucoup de choses sur les Hordes. Mais ce n\'est tout au plus qu\'une image imparfaite de la peur brutale qui vous assaille quand leurs assauts commencent.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_047', 'content' => 'Petite coalition entre amis, encore des soucis', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_048', 'content' => 'Petite pastille de cyanure... si petite et pourtant... une belle raclure...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_049', 'content' => 'Pourquoi hésiter, vendez votre âme !', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_050', 'content' => 'Qu\'est ce qui est plus gênant qu\'une tique ? Une tique de 3kg. On les rencontre une seule fois...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_051', 'content' => 'Qu\'est-ce que j\'aurais donné pour mourir une bonne fois pour toutes ce jour là et ne jamais avoir à revivre ça.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_052', 'content' => 'Qu\'est-ce qui pend, hirsute et malodorant ? Mon voisin, pendu, forcément...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_053', 'content' => 'Quand c\'est le chaos, fiez-vous à votre instinct.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_054', 'content' => 'Quand je l\'entends rire, j\'attends un instant puis je le pends.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_055', 'content' => 'Quand on vous dit blanc, pensez noir. Quand on vous dit noir, pensez blanc. Quand ça dure trop longtemps arrêtez de prendre des gants.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_056', 'content' => 'Quand tu prends dans la banque, n\'oublie pas le cyanure...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_057', 'content' => 'Qui a volé doit être pendu ! Qui a pendu doit être banni ! Qui est banni doit être dévoré ! Voilà ! Telle devrait être la loi !', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_058', 'content' => 'Qui craint le grand méchant loup ? Certainement pas le grand zombie...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_059', 'content' => 'Qui trahit est banni.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_060', 'content' => 'Qui veille bien ira loin...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_061', 'content' => 'Rira bien qui sera pendu le dernier.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_062', 'content' => 'Sept minutes. C\'est le temps qu\'il faut à un de ces zombies pour te bouffer et ne laisser que quelques osselets derrière lui. Neuf à la rigueur si tu gigotes.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_063', 'content' => 'Si on m\'avait dit que la pisse d\'un chat liquéfiait un zombie...', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_064', 'content' => 'Un jour, en creusant un peu dans ma cabane, j\'ai trouvé des bouts de gencives. Putréfiés.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_065', 'content' => 'Un jour, un pasteur est parti en croisade contre le Malin. Apparemment, ce dernier l\'était plus que lui. Il n\'est jamais revenu.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_066', 'content' => 'Un os par terre = un ami à terre', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_067', 'content' => 'Un seul moyen de se souvenir : lire une Chronique Citoyenne', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_068', 'content' => 'Une bonne goule est une... (propos inachevé pour cause de perte de langue inopinée)', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_069', 'content' => 'Vous avez assez réfléchi : fermez la porte. Vous n\'entendrez pas leurs cris depuis l\'intérieur.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_070', 'content' => 'Vous pensez que les autres pensent à vous ? Non, ils pensent : “OSEF !”. ', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_071', 'content' => 'Ça fait des heures qu\'ils hurlent derrière la porte, dehors. Mais maintenant qu\'il fait nuit, les insultes ont laissé place aux supplications.', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_072', 'content' => 'Être brave ou être mort. Quelle différence ?', 'author' => '', 'lang' => 'fr'],
        ['name' => 'fr_073', 'content' => '... Et elle cria toute la nuit, elle hurla même... Mais vous savez : on n\'oublie jamais.', 'author' => '', 'lang' => 'fr'],

        // English quotes
        ['name' => 'en_001', 'content' => '... and she screamed all night, yelled too... And you know what ? We\'ll never forget her.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_002', 'content' => 'A bone on the ground is a once lost friend, found.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_003', 'content' => 'A coalition between friends creates more problems than it ends...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_004', 'content' => 'Always keep TWO bullets in your gun. One to take the head off a zombie, and another for yourself if you miss...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_005', 'content' => 'Be brave or die. Why bother? Same end result...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_006', 'content' => 'Be positive ! You\'re going to die. Every time.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_007', 'content' => 'Before banning someone, make sure you can run...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_008', 'content' => 'Carnage... Defeat... Treason... Cowardice... These are the words of the day.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_009', 'content' => 'Chaos at night means you\'re right in', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_010', 'content' => 'Chaos mode is the cherry on the cake. But the candles are your neighbour\'s fingers... and the cherry a snagged testicle. Enjoy!', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_011', 'content' => 'Come on guys, open up! I promise not to do it again... Guys ?...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_012', 'content' => 'Cyanide... A jagged little pill indeed, but it beats listening to Ironic again... !', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_013', 'content' => 'Do you really think that others are thinking of you ? No, they\'re thinking, Bite Me!', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_014', 'content' => 'Don\'t forget : friends won\'t be there forever.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_015', 'content' => 'Don\'t get involved ! Seriously ! Run away !', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_016', 'content' => 'Drink or die ? Why choose ?', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_017', 'content' => 'Everybody in town loved the mayor. It\'s a real shame we finished eating him yesterday.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_018', 'content' => 'Facing 100 zombies, the best course of action is to haul ass !', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_019', 'content' => 'Finally, today, I understand everything...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_020', 'content' => 'For hours now they have wailed outside the gates. But now that midnight approaches, the citizens\' insults have turned into pleas for mercy.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_021', 'content' => 'Freedom is to be found in the white pill...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_022', 'content' => 'He who is hanged last, laughs loudest.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_023', 'content' => 'He who steals must be hanged ! He who commits someone to be hanged should be banned ! He who is banned must be eaten ! This is how the law should be !', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_024', 'content' => 'I always get quite emotional when I think about Heroes. They have their name forever etched in stone...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_025', 'content' => 'I know, I know. The food is awful but at least it doesn\'t move when you\'re trying to tuck in.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_026', 'content' => 'I love escaping, changing my skin and dreaming. I often do this when I\'m dead...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_027', 'content' => 'I\'m so hungry I could eat my neighbour. Having seen the Zombies\' vigour yesterday, it can\'t be that bad for you...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_028', 'content' => 'If you live in a hovel, for friends you must grovel', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_029', 'content' => 'If you\'re always on guard then you\'re gonna go far...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_030', 'content' => 'If you\'re on an expedition and you sense danger, stay calm,  wait a second, get your Nikes on and RUN FORREST, RUUUUUUNNNN !!!', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_031', 'content' => 'Jeremy told me : Just you watch, we\'re going to get them. Oh how they laughed when they hung him up by his feet...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_032', 'content' => 'My dog... I love him, what more is there to say ! He defends me and distracts me from this non-life... and if I get hungry...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_033', 'content' => 'My favourite thing about group expeditions is letting everyone do the searching and bogarting the good stuff !!!', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_034', 'content' => 'One day when I was looking around my cabin, I found some gums. The ones that usually hold teeth. Rotten, obviously.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_035', 'content' => 'One day, a pastor went on a crusade against the evil Horde. It would appear that there were more of the latter. He never returned.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_036', 'content' => 'One night, a long time ago, old Chuck saw the Horde and lived to tell the tale. When that happened he clenched his buttcheeks. They have not been unclenched since.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_037', 'content' => 'Our work here is done... that much is true... but at what price?', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_038', 'content' => 'Reading the newspaper is the only way to find out what\'s really been going on.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_039', 'content' => 'Seven minutes. Thats how long it takes a zombie to munch you and leave nothing but some bits that are too crunchy. In cases of extreme wriggling it can be as much as 9...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_040', 'content' => 'Since Carla died I haven\'t slept a wink in three days. What ? Yeah, I know that was last week, but she\'s still banging around in the cellar.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_041', 'content' => 'Since they munched old Rosie, there\'s no need to do any cleaning ! ... Aaaaaah ! Aunt Rose ! ! ...Nooooo ! !... arrrggh nnnngg...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_042', 'content' => 'Slapping your neighbour is as worthwhile as slapping yourself.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_043', 'content' => 'Sometimes behind a great hero you find an ass.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_044', 'content' => 'Sometimes when we\'re outside the walls, shadows appear right next to us. Enormous shadows ! And you never hear them coming...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_045', 'content' => 'Sometimes, when we\'re out and about, some sand moves and uncovers some remains. We never manage to identify them though...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_046', 'content' => 'The only good ghoul is a... (point not made due to sudden unexpected loss of voice).', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_047', 'content' => 'The soul never forgets. Don\'t forget it !', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_048', 'content' => 'The watcher always misses his missing eye.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_049', 'content' => 'There is only one way to forget : do I need to tell you what it is ?', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_050', 'content' => 'Traitors will be fed to the \'gators. Or they would be if \'gators weren\'t extinct. Traitors will be severely dealt with.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_051', 'content' => 'We had the choice of pardonning her or punishing her. In the end we built her a cremato-cue. Well, we\'ve got to eat !', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_052', 'content' => 'We tell a lot of stories about the Hordes, but that doesn\'t even give you a hint of the extreme fear that hits you when they attack...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_053', 'content' => 'What are you waiting for? Sell your soul !', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_054', 'content' => 'What is more irritating than a tick ? A tick weighing 3kg. We\'ve only come across them once...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_055', 'content' => 'What wouldn\'t I give to die today, once and for all, and never have to go through this again...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_056', 'content' => 'What\'s that hairy and foul smelling thing hanging there ? Ah, it\'s my neighbour. (Don\'t get ahead of me now...)', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_057', 'content' => 'When I hear someone laughing, I wait a moment, then hang them...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_058', 'content' => 'When the minds of others are dulled or distracted, it\'s time to make your move...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_059', 'content' => 'When they tell you black, think white. When they tell you white, think black. When this goes on for too long, just go without gloves.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_060', 'content' => 'When you steal from the bank, don\'t forget the cyanide pills...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_061', 'content' => 'When you\'re in the midst of chaos, trust your instinct.', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_062', 'content' => 'Who\'s afraid of the big bad wolf ? Certainly not the big zombie...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_063', 'content' => 'Why did nobody tell me that cat\'s piss vaporises zombies...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_064', 'content' => 'Yesterday, Jessica and I decided to share everything. Today, I\'m a Hero...', 'author' => '', 'lang' => 'en'],
        ['name' => 'en_065', 'content' => 'Yesterday, Joe told the three of us : The more of us there are, the stronger we are ! Let\'s go left ! We didn\'t go, we just left...', 'author' => '', 'lang' => 'en'],

        // German quotes
        ['name' => 'de_001', 'content' => '...sie schrie und brüllte die ganze Nacht... aber die Regeln waren für jeden klar: Es wird nichts vergessen, es wird nicht vergeben.', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_002', 'content' => 'Ach, die Sonne brennt so sehr und das Fläschchen ist schon leer... hat noch jemand etwas Twinoid?!', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_003', 'content' => 'Angriff ist zwar die beste Verteidigung, aber gegen hundert Zombies ist die beste Wahl immer noch die Flucht!', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_004', 'content' => 'Auf meinen Hund lasse ich nichts kommen! Ich liebe ihn! Er lenkt mich ab und verteidigt mich. Und falls ich mal Hunger habe...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_005', 'content' => 'Außerhalb der Stadt wirst du Schattengestalten begegnen, die oft in unsere Nähe kommen. Das Gemeine daran ist: Wenn du sie bemerkst, ist es oft zu spät.', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_006', 'content' => 'Baust du ne Baracke, steckst du in der Kacke.', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_007', 'content' => 'Chaos am Abend führt zu Kummer am Morgen...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_008', 'content' => 'Deine Seele vergisst nicht. Vergiss das nie!', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_009', 'content' => 'Der alte Chuck ist eines Nachts mal einer Zombiemeute begegnet... er ist lebend zurückgekehrt. Aber vor lauter Angst hat er seitdem sein Haus nicht mehr verlassen.', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_010', 'content' => 'Der Wächter denkt an sein fehlendes Auge.', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_011', 'content' => 'Diebe gehören gehängt, Henker verbannt. Und wer verbannt ist, der soll verrecken! So müsste es sein und nicht anders!', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_012', 'content' => 'Du hast lange gezögert und mit dir gerungen, doch es gibt nur eine vernünftige Lösung: Mach deine Tür zu. Von innen wirst du keinen Laut mehr hören, und überhaupt: So gut kanntest du deine Nachbarn auch wieder nicht...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_013', 'content' => 'Du hast nichts gesehen.... du hast nichts gehört...du hast nichts gesehen... du hast nichts gehört....', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_014', 'content' => 'Du wolltest nur noch deine Arbeit zu Ende bringen... Das ist dir gelungen... aber war es das wert ?', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_015', 'content' => 'Eine kleine Koalition unter Freunden und der Ärger ist vorprogrammiert...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_016', 'content' => 'Es gibt nur eine Methode, um zu vergessen. Muss ich das weiter ausführen?', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_017', 'content' => 'Freiheit verspricht nur die rosa Tablette...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_018', 'content' => 'Gemetzel, Niederlage, Verrat und Feigheit! Das sind die Wörter, die wir gebrauchen sollten!', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_019', 'content' => 'Gestern da waren wir zu dritt. Jan meinte: Je mehr wir sind, desto stärker sind wir! Lass uns nach links gehen!. Er ist allein abgebogen. Am nächsten Tag haben wir ihn dann dort eingesammelt...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_020', 'content' => 'Glaubst du wirklich, dass die anderen an dich denken? Nein, sie denken sich: Scheiß drauf!.', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_021', 'content' => 'Hebe dir bis zum Schluss immer ZWEI Kugeln auf. Eine für den Zombie und eine für dich - falls du ihn verfehlen solltest...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_022', 'content' => 'Heute... da ist mir so einiges klar geworden...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_023', 'content' => 'Hinter jedem Helden verbirgt sich potenziell auch eine schmutzige Kröte...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_024', 'content' => 'Ich habe seit drei Tagen sehr starke Bauchschmerzen... und fresse wie ein Blöder Natriumbikarbonat. Aber es hilft nichts. Was wäre, wenn es sich um einen dieser dreckigen Parasitenwürmer handelt, die dich von Innen auffressen?', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_025', 'content' => 'Ich habe so einen Hunger, ich würde sogar meinen Nachbarn essen. Wenn ich an die Kraft der Zombies von gestern Nacht zurück denke, kann das gar nicht so schlecht sein.', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_026', 'content' => 'Ich weiß, ich weiß... das Essen ist nicht gut. Immerhin läuft es nicht weg, wenn du reinbeißt.', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_027', 'content' => 'Im Chaos verlässt du dich besser auf deinen Instinkt.', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_028', 'content' => 'In der Not erkennt man seine Freunde, doch diese leben nicht ewig...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_029', 'content' => 'Jeder in der Stadt mochte unseren Pfarrer. Es ist wirklich schade, dass wir ihn gestern gegessen haben...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_030', 'content' => 'Jedesmal wenn wir zur Relaisstation gehen, finden wir neue Knochen am Boden. Wir können sie aber nie jemand genauem zuordnen...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_031', 'content' => 'Jochen sagte zu mir: Du wirst sehen, die kriegen wir noch. Sie haben köstlich gelacht, als sie ihn an den Füßen aufgehängt haben.', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_032', 'content' => 'Kommt schon, macht das Tor wieder auf! Ich mache es auch nicht wieder... Versprochen... Jungs?!...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_033', 'content' => 'Lebe chaotisch, sterbe exotisch!', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_034', 'content' => 'Mutig oder tot sein - wo liegt der Unterschied?', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_035', 'content' => 'Nur ein toter Zombie ist ein... (durch einen \'unerwarteten\' Zungenverlust konnte dieser Satz nicht zu Ende gebracht werden)', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_036', 'content' => 'Sehen ist anders als erzählt bekommen...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_037', 'content' => 'Seit Stunden brüllen sie da draußen. Jetzt, da die Nacht gekommen ist, sind Geschrei, Beleidigungen und Anschuldigungen Winsellauten und Klageseufzern gewichen...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_038', 'content' => 'Seitdem Carla tot ist, habe ich kein Auge mehr zugemacht. Wie bitte? Ja, ich weiß, das war letzte Woche... aber jetzt poltert sie unten im Keller rum.', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_039', 'content' => 'Sieben Minuten. Solange braucht ein Zombie, um dich mit Haut und Haaren zu fressen. Na gut, es können auch mal neun sein, wenn du allzu sehr herumzappelst...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_040', 'content' => 'Unmoral ist, wonach man sich schlecht fühlt...warum geht\'s mir dann so gut?', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_041', 'content' => 'Verräter werden verbannt.', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_042', 'content' => 'Was ich an Gruppenexpeditionen liebe: Die Anderen graben und ich sacke ein!', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_043', 'content' => 'Was ist zerzaust, stinkt und hängt in der Luft? Mein aufgehängter Nachbar, was sonst?', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_044', 'content' => 'Was würde ich nicht geben, einmal richtig zu sterben und nie wieder aufzuwachen?', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_045', 'content' => 'Was zögerst du noch? Verkaufe deine Seele!', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_046', 'content' => 'Wenn die Seele frei ist, gibt es in der Welt nichts mehr, das uns bindet...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_047', 'content' => 'Wenn die Seele frei ist, gibt es nichts mehr, das uns an diese Welt bindet...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_048', 'content' => 'Wenn er noch einmal so dreckig lacht, hänge ich ihn auf...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_049', 'content' => 'Wenn ich gewusst hätte, dass man mit Katzenpisse Zombies töten kann...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_050', 'content' => 'Wenn\'s auf einer Expedition mal brenzlig wird: Schalt \'nen Gang zurück und lass die anderen weitergehen...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_051', 'content' => 'Wer Großes vollbringen möchte, muss viele Nächte durchwandern.', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_052', 'content' => 'Wer gut auf sich aufpasst, kann es weit bringen...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_053', 'content' => 'Wer hat Angst vorm Schwarzen Mann? Die Zombies bestimmt nicht...', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_054', 'content' => 'Wir hatten die Wahl, sie entweder zu bestrafen oder ihr zu verzeihen. Wir haben uns schließlich dafür entschieden, sie im Kremato-Cue zu rösten. Wenigstens haben wir jetzt keinen Hunger mehr.', 'author' => '', 'lang' => 'de'],
        ['name' => 'de_055', 'content' => 'Wir versuchen uns alle irgendwann mal an einem Zombiestöhnen, aber wer hat schon mal ausprobiert, einen Menschen nachzumachen, dem soeben ein Stück Fleisch aus dem Leib gerissen wurde und der dabei zusehen muss, wie es vor seinen Augen verschlungen wird?Mutig oder tot sein - wo liegt der Unterschied?Was würde ich nicht geben, einmal richtig zu sterben und nie wieder aufzuwachen?Ich habe so einen Hunger, ich würde sogar meinen Nachbarn essen. Wenn ich an die Kraft der Zombies von gestern Nacht zurück denke, kann das gar nicht so schlecht sein.', 'author' => '', 'lang' => 'de'],

        // Spanish quotes
        ['name' => 'es_001', 'content' => 'A través de la ventana veo sombras pasar. Me da tanto miedo...', 'author' => '', 'lang' => 'es'],
        ['name' => 'es_002', 'content' => 'A veces al caminar, encontramos huesos en la arena. ¿Serán de algún compañero?', 'author' => '', 'lang' => 'es'],
        ['name' => 'es_003', 'content' => 'Ayer vi un perro sacudiendo un pedazo de carne, me pregunto si será la de su amo.', 'author' => '', 'lang' => 'es'],
        ['name' => 'es_004', 'content' => 'De noche, no puedo dormir pensando en que esos monstruos vendrán a devorarnos vivos.', 'author' => '', 'lang' => 'es'],
        ['name' => 'es_005', 'content' => 'Desde que mi amigo Pancho se hizo devorar, me siento muy triste, felizmente su novia me consuela...', 'author' => '', 'lang' => 'es'],
        ['name' => 'es_006', 'content' => 'Querido diario, hoy me salvé de mor_ (mancha de sangre)', 'author' => '', 'lang' => 'es'],
        ['name' => 'es_007', 'content' => 'Tengo tanta hambre que me comería a un vecino. Si a los zombies les gusta tanto, no deben saber tan mal...', 'author' => '', 'lang' => 'es'],
    ];

    private function insertAwards(ObjectManager $manager, ConsoleOutputInterface $out) {
        $out->writeln('<comment>Quotes: ' . count(static::$quotes_data) . ' fixture entries available.</comment>');

        $progress = new ProgressBar( $out->section() );
        $progress->start( count(static::$quotes_data) );

       foreach(static::$quotes_data as $entry) {
            $entity = $this->entityManager->getRepository(Quote::class)->findOneBy(['name' => $entry['name']]);

            if($entity === null) {
                $entity = new Quote();
                $entity->setName($entry['name']);
            }

            $entity
                ->setAuthor($entry['author'])
                ->setContent($entry['content'])
                ->setLang($entry['lang']);

            $manager->persist($entity);
            $progress->advance();
        }
        $manager->flush();
        $progress->finish();
    }

    public function __construct(EntityManagerInterface $em) {
        $this->entityManager = $em;
    }

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();
        $output->writeln( '<info>Installing fixtures: Quotes Database</info>' );
        $output->writeln("");

        $this->insertAwards($manager, $output);
        $output->writeln("");
    }

    /**
     * @inheritDoc
     */
    public function getDependencies()
    {
        return [ PictoFixtures::class ];
    }
}
