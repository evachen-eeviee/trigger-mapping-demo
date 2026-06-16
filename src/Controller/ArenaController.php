<?php

namespace App\Controller;

use App\Entity\Gladiator;
use App\Form\GladiatorType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use function Symfony\Component\String\s;

class ArenaController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('arena/home.html.twig');
    }

    #[Route('/create', name: 'app_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $gladiator = new Gladiator('', 34, 33, 33);
        $form = $this->createForm(GladiatorType::class, $gladiator);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($gladiator);
            $em->flush();
            $this->addFlash('success', "{$gladiator->name} a été forgé pour la gloire !");
            return $this->redirectToRoute('app_select');
        }

        return $this->render('arena/create.html.twig', ['form' => $form->createView()]);
    }
    #[Route('/select', name: 'app_select', methods: ['GET'])]
    public function select(EntityManagerInterface $em): Response
    {
        $gladiators = $em->getRepository(Gladiator::class)->findAll();
        return $this->render('arena/select.html.twig', ['gladiators' => $gladiators]);
    }

    #[Route('/login/{id}', name: 'app_login', methods: ['GET'])]
    public function login(Gladiator $gladiator, Request $request): Response
    {
        if ($gladiator->status === 'exhausted') {
            $this->addFlash('error', "Attention, {$gladiator->name} est épuisé ! Tu devras le reposer avant de faire quoi que ce soit.");
        }
        $request->getSession()->set('player_id', $gladiator->id);
        return $this->redirectToRoute('app_play');
    }
    #[Route('/play', name: 'app_play', methods: ['GET'])]
    public function play(Request $request, EntityManagerInterface $em): Response
    {
        $playerId = $request->getSession()->get('player_id');
        if (!$playerId) return $this->redirectToRoute('app_select');

        $player = $em->getRepository(Gladiator::class)->find($playerId);

        return $this->render('arena/play.html.twig', ['player' => $player]);
    }

    #[Route('/action/{type}', name: 'app_action', methods: ['GET'])]
    public function action(string $type, Request $request, EntityManagerInterface $em): Response
    {
        $player = $em->getRepository(Gladiator::class)->find($request->getSession()->get('player_id'));
        if (!$player) return $this->redirectToRoute('app_home');

        // BLOQUER L'ENTRAINEMENT SI ÉPUISÉ
        if ($player->status === 'exhausted' && $type === 'train') {
            $this->addFlash('error', "Tu es trop faible pour t'entraîner. Repose-toi !");
            return $this->redirectToRoute('app_play');
        }

        if ($player->actionCount >= 3 && $type !== 'rest') {
            $this->addFlash('error', "Le public s'ennuie ! Tu DOIS combattre !");
            return $this->redirectToRoute('app_play');
        }

        $logs = [];

        if ($player->status !== 'exhausted') {
            $player->actionCount = $player->actionCount + 1;
        }

        if ($type === 'train') {
            if (rand(0, 1) === 0) {
                $gain = rand(2, 6);
                $player->statAtkPercent  = (min(75, $player->statAtkPercent+ $gain));
                $logs[] = "🗡️ Ton attaque passe à {$player->statAtkPercent}% (+{$gain}) !";
            } else {
                $gain = rand(2, 6);
                $player->statDefPercent = (min(75, $player->statDefPercent + $gain));
                $logs[] = "🛡️ Ta défense passe à {$player->statDefPercent}% (+{$gain}) !";
            }
        } elseif ($type === 'rest') {
            $gain = rand(10, 30);
            $player->healthPoints = (min(200, $player->healthPoints + $gain));
            $logs[] = "💤 Tu te reposes et récupères des forces. Tes PV sont à {$player->healthPoints} (+{$gain} HP).";

            if ($player->status === 'exhausted') {
                $logs[] = "✨ Miracle ! Tu as repris tes esprits. Tu peux à nouveau combattre !";
                $player->actionCount = 0;
            }
        }

        $em->flush();

        return $this->render('arena/summary.html.twig', [
            'title' => 'Bilan de l\'action',
            'logs' => $logs
        ]);
    }

    #[Route('/fight/list', name: 'app_fight_list', methods: ['GET'])]
    public function fightList(Request $request, EntityManagerInterface $em): Response
    {
        $playerId = $request->getSession()->get('player_id');
        $player = $em->getRepository(Gladiator::class)->find($playerId);

        if ($player->status === 'exhausted') {
            $this->addFlash('error', "Tu tiens à peine debout, impossible de combattre !");
            return $this->redirectToRoute('app_play');
        }

        $opponents = $em->getRepository(Gladiator::class)->createQueryBuilder('g')
            ->where('g.status = :status')->setParameter('status', 'alive')
            ->andWhere('g.id != :id')->setParameter('id', $playerId)
            ->getQuery()->getResult();

        return $this->render('arena/fight_list.html.twig', ['player' => $player, 'opponents' => $opponents]);
    }

    /**
     * @throws ORMException
     */
    #[Route('/fight/duel/{id}', name: 'app_fight_duel', methods: ['GET'])]
    public function fightDuel(Gladiator $target, Request $request, EntityManagerInterface $em): Response
    {
        $player = $em->getRepository(Gladiator::class)->find($request->getSession()->get('player_id'));
        if (!$player) return $this->redirectToRoute('app_home');

        $player->actionCount = 0;

        $dmgToTarget = max(5, (int)(rand(10, 25) + ($player->statAtkPercent / 2) - ($target->statDefPercent / 3)));
        $dmgToPlayer = max(5, (int)(rand(10, 25) + ($target->statAtkPercent / 2) - ($player->statDefPercent / 3)));

        $target->healthPoints = (int)($target->healthPoints - $dmgToTarget);
        $player->healthPoints = (int)($player->healthPoints - $dmgToPlayer);

        $atkLoss = rand(1, 5);
        $defLoss = rand(1, 5);

        $player->statAtkPercent = max(5, $player->statAtkPercent - $atkLoss);
        $player->statDefPercent = max(5, $player->statDefPercent - $defLoss);

        $target->statAtkPercent = max(5, $target->statAtkPercent - rand(1, 5));
        $target->statDefPercent = max(5, $target->statDefPercent - rand(1, 5));

        $em->flush();
        $em->refresh($player);
        $em->refresh($target);

        $logs = [
            "⚔️ Tu attaques {$target->name} et infliges {$dmgToTarget} dégâts !",
            "🩸 {$target->name} riposte et t'inflige {$dmgToPlayer} dégâts !",
            "📉 La fatigue du combat te fait perdre -{$atkLoss}% ATK et -{$defLoss}% DEF."
        ];

        if ($target->status === 'exhausted') $logs[] = "🏆 VICTOIRE ! {$target->name} s'effondre d'épuisement (1 PV) !";
        if ($player->status === 'exhausted') $logs[] = "💀 DÉFAITE ! Tu t'écroules d'épuisement (1 PV)... Repose-toi.";

        return $this->render('arena/summary.html.twig', [
            'title' => 'Rapport de Combat',
            'logs' => $logs
        ]);
    }
}
