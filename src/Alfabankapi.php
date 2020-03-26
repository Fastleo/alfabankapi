<?php

namespace Agenta\Alfabankapi;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Class Alfabankapi
 * @package Agenta\Alfabankapi
 */
class Alfabankapi
{
    protected $client;
    protected $apiUrl;
    protected $login;
    protected $token;
    protected $password;
    protected $callbackUrl;
    protected $lang;

    protected $orderStatus = [
        0 => 'Заказ зарегистрирован, но не оплачен',
        1 => 'Предавторизованная сумма захолдирована (для двухстадийных платежей)',
        2 => 'Проведена полная авторизация суммы заказа',
        3 => 'Авторизация отменена',
        4 => 'По транзакции была проведена операция возврата',
        5 => 'Инициирована авторизация через ACS банка-эмитента',
        6 => 'Авторизация отклонена'
    ];

    protected $errorCodes = [
        0 => 'Обработка запроса прошла без системных ошибок',
        2 => 'Заказ отклонен по причине ошибки в реквизитах платежа',
        5 => 'Доступ запрещён / Пользователь должен сменить свой пароль / orderId не указан',
        6 => 'Неверный номер заказа',
        7 => 'Системная ошибка'
    ];

    protected $errorCodesExtended = [
        0 => 'Обработка запроса прошла без системных ошибок',
        1 => 'Ожидается orderId или orderNumber',
        5 => 'Доступ запрещён / Пользователь должен сменить свой пароль',
        6 => 'Заказ не найден',
        7 => 'Системная ошибка',
    ];

    protected $orderCreateErrors = [
        0 => 'Обработка запроса прошла без системных ошибок',
        1 => 'Заказ с таким номером уже обработан. Заказ с таким номером был зарегистрирован, но не был оплачен. Неверный номер заказа',
        3 => 'Неизвестная валюта',
        4 => 'Номер заказа не может быть пуст. Имя мерчанта не может быть пустым. Отсутствует сумма. URL возврата не может быть пуст. Пароль не может быть пуст',
        5 => 'Логин продавца неверен. Неверная сумма. Неправильный параметр [Язык]. Доступ запрещён. Пользователь должен сменить свой пароль. Доступ запрещён . jsonParams неверен.',
        7 => 'Системная ошибка',
        13 => 'Использование обоих значений Features FORCETDS/FORCESSL и AUTO_PAYMENT недопустимо. Мерчант не имеет привилегии выполнять AUTO платежи. Мерчант не имеет привилегии выполнять проверочные платежи.',
        14 => 'Features указаны некорректно'
    ];

    public function __construct()
    {
        $this->apiUrl = env('ALFABANK_API_URL');
        $this->login = env('ALFABANK_LOGIN');
        $this->password = env('ALFABANK_PASSWORD');
        $this->token = env('ALFABANK_TOKEN');
        $this->callbackUrl = env('ALFABANK_URL_STATUS');
        $this->lang = env('ALFABANK_LANG');
        $this->client = new Client();
    }


    /**
     * Создание заказа на оплау
     *
     * @param string $orderNumber ID заказа в системе мерчанта
     * @param int $amount Сумма оплаты в копейках
     * @param string $description Описание заказа (оплата за ...)
     *
     * @return bool|mixed
     */
    public function orderRegister(
        string $orderNumber,
        int $amount,
        string $description = ''
    )
    {
        //строка описания должна быть максимум 90 символов и не содержать %+\n\r
        $description = mb_substr(str_replace(['%', '+', "\n", "\r"], '', $description), 0, 90);

        $params = [
            'userName' => $this->login,
            'password' => $this->password,
//            'token' => '',
            'orderNumber' => $orderNumber,
            'amount' => $amount,
            'currency' => 980,
            'returnUrl' => $this->callbackUrl,
            'failUrl' => $this->callbackUrl,
            'sessionTimeoutSecs' => 600,
            'ip' => request()->ip(),
//            'email' => 'info@a-b.com.ua',
            'description' => $description,
            'language' => $this->lang,
        ];

        if ($request = $this->sendAPI('/register.do', $params)) {
            if (isset($request->orderId)) {
                return $request;
            }
        }

        Log::error(get_class($this) . ' -> ' . __FUNCTION__, $params);
        return false;
    }

    /**
     * Проверка статуса заказа
     *
     * @param string $orderId ID заказа в системе Альфабанк
     * @return bool|mixed
     */
    public function orderStatus(string $orderId)
    {
        $params = [
            'userName' => $this->login,
            'password' => $this->password,
            'orderId' => $orderId,
            'language' => $this->lang,
        ];
        if ($request = $this->sendAPI('/getOrderStatus.do', $params)) {
            return $request;
        }

        Log::error(get_class($this) . ' -> ' . __FUNCTION__);
        return false;
    }

    /**
     * Расширенная проверка статуса заказа
     *
     * @param string $orderId ID заказа в системе Альфабанк
     * @param string $orderNumber ID заказа мерчанта
     * @return bool|mixed
     */
    public function orderStatusExtended(string $orderId, string $orderNumber)
    {
        $params = [
            'userName' => $this->login,
            'password' => $this->password,
            'orderId' => $orderId,
            'orderNumber' => $orderNumber,
            'language' => $this->lang,
        ];
        if ($request = $this->sendAPI('/getOrderStatusExtended.do', $params)) {
            return $request;
        }

        Log::error(get_class($this) . ' -> ' . __FUNCTION__);
        return false;
    }


    /**
     * Возвращает описание ошибки запроса статуса
     *
     * @param $errorCode
     * @return mixed
     */
    public function getErrorCode($errorCode)
    {
        if (isset($this->errorCodes[$errorCode])) {
            return $this->errorCodes[$errorCode];
        }
        return 'неизвестная ошибка';
    }

    /**
     * Вовзращает описание ошибки для запроса расширенного статуса
     *
     * @param $errorCode
     * @return mixed|string
     */
    public function getErrorCodeExtended($errorCode)
    {
        if (isset($this->errorCodesExtended[$errorCode])) {
            return $this->errorCodesExtended[$errorCode];
        }
        return 'неизвестная ошибка';
    }

    /**
     * Возвращает описание статуса заказа
     *
     * @param $orderStatus
     * @return mixed
     */
    public function getOrderStatus($orderStatus)
    {
        if (isset($this->orderStatus[$orderStatus])) {
            return $this->orderStatus[$orderStatus];
        }
        return 'статус заказа не определен';
    }


    /**
     * Список платежей
     *
     * @param string $from дата с YYYY-MM-DD H:i:s
     * @param string $to дата по YYYY-MM-DD H:i:s
     * @param int $page номер страницы, которую отобразить
     * @param bool $includeNew отображать заказ со статусом CREATED и DECLINED
     * @param string $states список статусов для отображения
     * @return bool|mixed
     */
    public function paymentsList(
        string $from,
        string $to,
        int $page,
        bool $includeNew = false,
        $states = 'CREATED,APPROVED,DEPOSITED,DECLINED,REVERSED,REFUNDED')
    {

        try {
            $from = Carbon::parse($from)->format('YmdHis');
        } catch (\Exception $e) {
            Log::error(get_class($this) . ' -> ' . __FUNCTION__ . ' ' . $e->getMessage());
            return false;
        }

        try {
            $to = Carbon::parse($to)->format('YmdHis');
        } catch (\Exception $e) {
            Log::error(get_class($this) . ' -> ' . __FUNCTION__ . ' ' . $e->getMessage());
            return false;
        }

        $params = [
            'userName' => $this->login,
            'password' => $this->password,
            'size' => 200,
            'from' => $from,
            'to' => $to,
            'transactionStates' => $states,
            'merchants' => '',
            'page' => $page,
            'searchByCreatedDate' => $includeNew, //false by default
            'language' => $this->lang,
        ];


        if ($request = $this->sendAPI('/getLastOrdersForMerchants.do', $params)) {
            if ($request->errorCode === 0) {
                return $request;
            }
        }

        return false;

    }

    /**
     * Возвращает результат успешности платежа
     *
     * @param Request $request
     * @return bool
     */
    public function callbackStatus(Request $request)
    {
        if ($request->has(['orderId', 'lang'])) {
            $orderId = $request->orderId;
            if ($status = $this->orderStatus($orderId)) {
                if (isset($status->ErrorCode) || isset($status->OrderStatus)) {
                    if ($status->OrderStatus === 2 | $status->OrderStatus === 1) {
                        return true;
                    }
                }
            }
        }

        return true;

    }

    /**
     * Отправка запроса на API сервер
     *
     * @param string $apiMethod
     * @param array $params
     * @return bool|mixed
     */
    private function sendAPI(string $apiMethod, array $params)
    {
        $url = $this->apiUrl . $apiMethod;
        try {
            $create = $this->client->request('POST', $url, [
                'headers' => [
//                    'Content-Type' => 'application/json',
                ],
                'allow_redirects' => true,
                'exceptions' => true,
                'decode_content' => true,
                'verify' => false,
                'form_params' => $params
            ]);
        } catch (\Exception $e) {
//            echo '[!] ошибка обращения к API серверу: ' . $e->getMessage() . PHP_EOL;
            Log::error(get_class($this) . ' -> ' . __FUNCTION__ . ' ' . $e->getMessage());
            return false;
        }

        if ($create->getStatusCode() === 200) {
            try {
                $result = json_decode($create->getBody()->getContents(), false);
            } catch (\Exception $e) {
                return false;
            }
            return $result;
        }
        return false;
    }

}
