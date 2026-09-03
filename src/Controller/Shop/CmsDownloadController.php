<?php
declare(strict_types=1);
namespace App\Controller\Shop;
use App\Cms\CmsDownloadStorage;
use App\Entity\Cms\CmsDownload;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
final class CmsDownloadController extends AbstractController { #[Route('/downloads/file/{id}',name:'cardnext_cms_download_file',methods:['GET'],requirements:['id'=>'\d+'])] public function __invoke(CmsDownload $download,ChannelContextInterface $channels,LocaleContextInterface $locales,CmsDownloadStorage $storage):Response{if(!$download->isPublished()||!$download->getChannels()->contains($channels->getChannel())||$download->getTranslation($locales->getLocaleCode())===null)throw $this->createNotFoundException();if($url=$download->getExternalUrl()){if(!filter_var($url,FILTER_VALIDATE_URL)||parse_url($url,PHP_URL_SCHEME)!=='https')throw $this->createNotFoundException();return new RedirectResponse($url);} $stored=$download->getFilePath();if($stored===null||!$storage->exists($stored))throw $this->createNotFoundException();$response=new BinaryFileResponse($storage->path($stored));$response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT,$download->getOriginalFilename()?:'download');$response->headers->set('X-Content-Type-Options','nosniff');if($download->getMimeType())$response->headers->set('Content-Type',$download->getMimeType());return $response;}}
