# 🏛️ Gladiator Arena — Démo Trigger Mapping Bundle

Démo officielle du bundle `talleu/trigger-mapping` mettant en scène des combats de gladiateurs pour illustrer la gestion déclarative des triggers SQL sous Symfony.

## 🚀 Lancement Rapide

Enchaîne simplement ces deux commandes dans ton terminal :

```bash
# 1. Installer et configurer l'environnement complet (Docker + DB + Triggers)
make build install

# 2. Lancer un combat et voir les triggers SQL agir en tâche de fond
make fight
```

## 🛠️ Commandes Utiles

- ``make start`` : Démarre l'environnement
- ``make stop`` : Arrête les conteneurs
- ``make validate`` : Lance la commande de validation du bundle (idéal pour tester le comportement CI).

---

## 📋 Complément de la TODO List pour tes scripts Symfony

Une fois que tes conteneurs tournent avec `make build install`, voici le code minimal pour la commande de simulation `make fight` (`src/Command/FightCommand.php`) :

```php
namespace App\Command;

use App\Entity\Gladiator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:fight', description: 'Simule un combat dans l\'arène')]
class FightCommand extends Command
{
    public function __construct(private EntityManagerInterface $em) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // 1. Trouver ou créer un gladiateur de test
        $gladiator = $this->em->getRepository(Gladiator::class)->findOneBy([]) 
            ?? new Gladiator('Maximus', 100);
        
        if ($gladiator->getHealthPoints() <= 0) {
            $gladiator->setHealthPoints(100); // Ressusciter pour la démo
            $gladiator->setStatus('alive');
            $this->em->persist($gladiator);
        }

        // 2. Simuler de gros dégâts
        $damage = rand(40, 110);
        $io->writeln(sprintf("⚔️ Maximus (HP: %d) subit un coup de %d dégâts !", $gladiator->getHealthPoints(), $damage));
        
        $gladiator->setHealthPoints($gladiator->getHealthPoints() - $damage);
        $this->em->flush(); // On applique uniquement la modification des HP en PHP

        // 3. Recharger l'entité pour prouver que le TRIGGER SQL a modifié le statut en BDD
        $this->em->refresh($gladiator);
        
        $io->info(sprintf("Résultat en BDD -> HP restants: %d | Statut actuel: %s", 
            $gladiator->getHealthPoints(), 
            $gladiator->getStatus()
        ));

        return Command::SUCCESS;
    }
}
