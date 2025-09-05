<?php

namespace App\Providers\Alioss;

use App\Services\ConfigService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use App\Providers\Alioss\AliOssAdapter;
use App\Providers\Alioss\Plugins\PutFile;
use App\Providers\Alioss\Plugins\PutRemoteFile;
use OSS\OssClient;
use Illuminate\Support\Facades\Log;

class AliOssServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('alioss', function($app, $config)
        {
            $configService = new ConfigService;
            $config = json_decode($configService->getFormatConfig('alioss'),true);
            $accessId  = $config['access_id'];
            $accessKey = $config['access_key'];

            $cdnDomain = empty($config['cdnDomain']) ? '' : $config['cdnDomain'];
            $bucket    = $config['bucket'];
            $ssl       = empty($config['ssl']) ? false : $config['ssl'];
            $isCname   = empty($config['isCName']) ? false : $config['isCName'];
            $debug     = env('APP_DEBUG');

            $endPoint  = $config['endpoint']; // 默认作为外部节点
            // dd('http://'.$endPoint);
            $epInternal= $isCname?$cdnDomain:(empty($config['endpoint_internal']) ? $endPoint : $config['endpoint_internal']); // 内部节点

            if($debug) Log::channel('qwlog')->debug('OSS config:', $config);

            $client  = new OssClient($accessId, $accessKey, $epInternal, $isCname);
            $adapter = new AliOssAdapter($client, $bucket, $endPoint, $ssl, $isCname, $debug, $cdnDomain);

            $filesystem =  new Filesystem($adapter);

            $filesystem->addPlugin(new PutFile());
            $filesystem->addPlugin(new PutRemoteFile());
            //$filesystem->addPlugin(new CallBack());
            return $filesystem;
        });
    }
}
