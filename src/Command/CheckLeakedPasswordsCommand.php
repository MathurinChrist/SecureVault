<?php

namespace App\Command;

use App\Entity\Alert;
use App\Entity\PasswordEntry;
use App\Repository\PasswordEntryRepository;
use App\Repository\UserRepository;
use App\Service\EncryptionService;
use App\Service\PwnedPasswordService;
use App\Service\VaultKeyProvider;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'securevault:check-leaked-passwords',
    description: 'Checks all password entries against the HaveIBeenPwned API (k-anonymity) and creates alerts for compromised passwords.',
)]
class CheckLeakedPasswordsCommand extends Command
{
    public function __construct(
        private readonly PasswordEntryRepository $passwordEntryRepository,
        private readonly UserRepository $userRepository,
        private readonly EncryptionService $encryptionService,
        private readonly PwnedPasswordService $pwnedService,
        private readonly EntityManagerInterface $em,
        private readonly VaultKeyProvider $vaultKeyProvider,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('user-id', 'u', InputOption::VALUE_OPTIONAL, 'Limit check to a specific user ID')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report without creating alerts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $userId = $input->getOption('user-id');

        $io->title('SecureVault — Leaked Password Check (HaveIBeenPwned)');

        if ($dryRun) {
            $io->note('Dry-run mode: no alerts will be created.');
        }

        // Guard against "0" (falsy) and non-numeric input, which the old ternary silently turned
        // into "scan everyone".
        $criteria = [];
        if ($userId !== null) {
            if (!ctype_digit((string) $userId)) {
                $io->error('--user-id must be a positive integer.');
                return Command::INVALID;
            }
            $criteria = ['user' => (int) $userId];
        }

        $entries  = $this->passwordEntryRepository->findBy($criteria);

        $io->progressStart(\count($entries));

        $breachedCount = 0;
        $skipped       = 0;

        foreach ($entries as $entry) {
            $io->progressAdvance();

            // Every entry is decryptable server-side: the vault DEK is unwrapped with the
            // master key, no user session required.
            try {
                $key       = $this->vaultKeyProvider->getOrCreateKey($entry->getVault());
                $plaintext = $this->encryptionService->decrypt($entry->getEncryptedPassword(), $key);
                $count     = $this->pwnedService->countBreaches($plaintext);
            } catch (\Throwable $e) {
                $skipped++;
                $this->logger->error('Leak check failed for entry #{id}: {msg}', [
                    'id'  => $entry->getId(),
                    'msg' => $e->getMessage(),
                ]);
                continue;
            }

            if ($count === 0) {
                continue;
            }

            ++$breachedCount;

            $io->writeln(sprintf(
                "\n  <error>LEAKED</error> #%d \"%s\" (user %d) — found %s time(s) in breaches",
                $entry->getId(),
                $entry->getTitle(),
                $entry->getUser()->getId(),
                number_format($count)
            ));

            if (!$dryRun) {
                $this->createAlert($entry, $count);
            }
        }

        $io->progressFinish();

        $this->em->flush();

        $io->success(sprintf(
            'Checked %d entries (%d skipped) — %d leaked password(s) %s.',
            \count($entries) - $skipped,
            $skipped,
            $breachedCount,
            $dryRun ? 'found (dry-run)' : 'flagged with alerts'
        ));

        return Command::SUCCESS;
    }

    private function createAlert(PasswordEntry $entry, int $count): void
    {
        $alert = (new Alert())
            ->setTitle(sprintf('Mot de passe compromis : %s', $entry->getTitle()))
            ->setDescription(sprintf(
                'Le mot de passe "%s" a été trouvé %s fois dans des fuites de données connues (source : HaveIBeenPwned). Changez-le immédiatement.',
                $entry->getTitle(),
                number_format($count)
            ))
            ->setType('danger')
            ->setCategory('security')
            ->setUser($entry->getUser());

        $this->em->persist($alert);
    }
}
