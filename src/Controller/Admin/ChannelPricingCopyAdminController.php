<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Channel\Channel;
use App\Pricing\ChannelPricingCopyService;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class ChannelPricingCopyAdminController extends AbstractController
{
    #[Route('/admin/catalog/channel-prices/copy', name: 'cardnext_admin_channel_pricing_copy', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, EntityManagerInterface $entityManager, ChannelPricingCopyService $service): Response
    {
        $channels = $entityManager->getRepository(Channel::class)->findBy(['enabled' => true], ['name' => 'ASC']);
        $preview = null;
        $values = ['source' => '', 'target' => '', 'adjustment' => '0.00', 'overwrite' => false];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('channel-pricing-copy-preview', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            $values = ['source' => (string) $request->request->get('source'), 'target' => (string) $request->request->get('target'), 'adjustment' => (string) $request->request->get('adjustment', '0'), 'overwrite' => $request->request->getBoolean('overwrite')];
            $source = $entityManager->getRepository(Channel::class)->findOneBy(['code' => $values['source'], 'enabled' => true]);
            $target = $entityManager->getRepository(Channel::class)->findOneBy(['code' => $values['target'], 'enabled' => true]);

            try {
                if (!$source instanceof Channel || !$target instanceof Channel) {
                    throw new \InvalidArgumentException('Bitte zwei aktivierte Channels auswählen.');
                }
                $preview = $service->copy($source, $target, $values['adjustment'], $values['overwrite'], true);
                $request->getSession()->set('channel_pricing_copy', $values);
            } catch (\InvalidArgumentException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('admin/cardnext/channel_pricing_copy/index.html.twig', ['channels' => $channels, 'preview' => $preview, 'values' => $values]);
    }

    #[Route('/admin/catalog/channel-prices/copy/execute', name: 'cardnext_admin_channel_pricing_copy_execute', methods: ['POST'])]
    public function execute(Request $request, EntityManagerInterface $entityManager, ChannelPricingCopyService $service): Response
    {
        if (!$this->isCsrfTokenValid('channel-pricing-copy-execute', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $values = $request->getSession()->remove('channel_pricing_copy');
        if (!is_array($values)) {
            $this->addFlash('error', 'Die Vorschau ist abgelaufen oder wurde bereits ausgeführt.');

            return $this->redirectToRoute('cardnext_admin_channel_pricing_copy');
        }
        if (!isset($values['source'], $values['target'], $values['adjustment'], $values['overwrite']) || !is_string($values['source']) || !is_string($values['target']) || !is_string($values['adjustment']) || !is_bool($values['overwrite'])) {
            throw $this->createNotFoundException('Invalid preview data.');
        }
        $source = $entityManager->getRepository(Channel::class)->findOneBy(['code' => $values['source'], 'enabled' => true]);
        $target = $entityManager->getRepository(Channel::class)->findOneBy(['code' => $values['target'], 'enabled' => true]);
        if (!$source instanceof Channel || !$target instanceof Channel) {
            throw $this->createNotFoundException('Channel not found.');
        }
        $result = $service->copy($source, $target, $values['adjustment'], $values['overwrite'], false);
        $this->addFlash('success', sprintf('Die Channelpreise wurden erfolgreich übertragen. %d erstellt, %d übersprungen, %d überschrieben.', $result->created, $result->skipped(), $result->overwritten));

        return $this->redirectToRoute('cardnext_admin_channel_pricing_copy');
    }
}
