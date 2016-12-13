<?php

namespace jumper423\decaptcha\core;

use Exception;

/**
 * Class DeCaptchaAbstract.
 */
abstract class DeCaptchaAbstract implements DeCaptchaInterface
{
    const RESPONSE_TYPE_STRING = 0;
    const RESPONSE_TYPE_JSON = 1;

    /**
     * Сервис на который будем загружать капчу.
     *
     * @var string
     */
    public $domain;

    public $errorLang = DeCaptchaErrors::LANG_EN;

    public $responseType = self::RESPONSE_TYPE_STRING;
    /**
     * Ваш API key.
     *
     * @var string
     */
    protected $apiKey;
    /**
     * @var int
     */
    protected $captchaId;

    protected $inUrl = 'in.php';

    /**
     * @return void
     */
    abstract public function notTrue();

    public function setApiKey($apiKey)
    {
        $this->apiKey = is_callable($apiKey) ? $apiKey() : $apiKey;
    }

    abstract protected function decodeResponse($data, $type, $format = self::RESPONSE_TYPE_STRING);

    /**
     * Узнаём путь до файла
     * Если передана ссылка, то скачиваем и кладём во временную директорию.
     *
     * @param string $fileName
     *
     * @throws Exception
     *
     * @return string
     */
    protected function getFilePath($fileName)
    {
        if (strpos($fileName, 'http://') !== false || strpos($fileName, 'https://') !== false) {
            try {
                $current = file_get_contents($fileName);
            } catch (\Exception $e) {
                throw new DeCaptchaErrors(DeCaptchaErrors::ERROR_FILE_IS_NOT_LOADED, $fileName, $this->errorLang);
            }
            $path = tempnam(sys_get_temp_dir(), 'captcha');
            if (!file_put_contents($path, $current)) {
                throw new DeCaptchaErrors(DeCaptchaErrors::ERROR_WRITE_ACCESS_FILE, null, $this->errorLang);
            }
            return $path;
        }
        if (file_exists($fileName)) {
            return $fileName;
        }
        throw new DeCaptchaErrors(DeCaptchaErrors::ERROR_FILE_NOT_FOUND, $fileName, $this->errorLang);
    }

    /**
     * @return string
     */
    protected function getBaseUrl()
    {
        return "http://{$this->domain}/";
    }

    /**
     * @param string $action
     *
     * @return string
     */
    protected function getActionUrl($action)
    {
        return "{$this->getBaseUrl()}res.php?key={$this->apiKey}&action={$action}&id={$this->captchaId}";
    }

    /**
     * @param string $action
     *
     * @return string
     */
    protected function getResponse($action)
    {
        return file_get_contents($this->getActionUrl($action));
    }

    /**
     * @return string
     */
    protected function getInUrl()
    {
        return $this->getBaseUrl() . $this->inUrl;
    }

    /**
     * Проверка на то произошла ли ошибка.
     *
     * @param $error
     *
     * @throws DeCaptchaErrors
     */
    protected function isError($error)
    {
        if (strpos($error, 'ERROR') !== false) {
            throw new DeCaptchaErrors($error, null, $this->errorLang);
        }
    }

    protected $lastRunTime = null;

    /**
     * Задержка выполнения.
     *
     * @param int $delay Количество секунд
     * @param \Closure|null $callback
     *
     * @return mixed
     */
    protected function executionDelayed($delay = 0, $callback = null)
    {
        $time = microtime(true);
        $timePassed = $time - $this->lastRunTime;
        if ($timePassed < $delay) {
            usleep(($delay - $timePassed) * 1000000);
        }
        $this->lastRunTime = microtime(true);

        return $callback instanceof \Closure ? $callback($this) : $callback;
    }

    /**
     * @param $postData
     *
     * @throws Exception
     *
     * @return string
     */
    protected function getCurlResponse($postData)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getInUrl());
        if (version_compare(PHP_VERSION, '5.5.0') >= 0 && version_compare(PHP_VERSION, '7.0') < 0 && defined('CURLOPT_SAFE_UPLOAD')) {
            curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new DeCaptchaErrors(DeCaptchaErrors::ERROR_CURL, curl_error($ch), $this->errorLang);
        }
        curl_close($ch);

        return $result;
    }
}
