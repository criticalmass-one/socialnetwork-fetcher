<?php declare(strict_types=1);

namespace App;

use App\DependencyInjection\Compiler\SocialNetworkFetcherPass;
use App\DependencyInjection\Compiler\SocialNetworkPass;
use App\NetworkFeedFetcher\NetworkFeedFetcherInterface;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/*
 * Config/route loading (config/packages/{,<env>/}*, services{,_<env>}.yaml,
 * config/routes/{,<env>/}*, routes.yaml) is done by MicroKernelTrait's defaults.
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new SocialNetworkPass());
        $container->registerForAutoconfiguration(NetworkInterface::class)->addTag('social_network.network');

        $container->addCompilerPass(new SocialNetworkFetcherPass());
        $container->registerForAutoconfiguration(NetworkFeedFetcherInterface::class)->addTag('social_network.network_feed_fetcher');
    }
}
