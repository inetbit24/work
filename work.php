
/**
 * @property Seller $Seller
 */

abstract class Abstracts
{
  
  private $id;
  private string $type = '';
  private string $name = '';
  private int $resellerId;
  private bool $mobile = true;

  private string $email = '';

  abstract public static function getById(int $resellerId): self;

  public function __construct(int $resellerId)
  {
    $this->resellerId = $resellerId;
    $this->id = $resellerId;
  }

  public function getType(): string
  {
    return $this->type;
  }

  public function getId(): int
  {
    return $this->id;
  }

  public function getName(): string
  {
    return $this->name;
  }

  public function getEmail(): string
  {
    return $this->email;
  }

  public function getFullName(): string
  {
    return $this->getName() . ' ' . $this->getId();
  }

  public function getMobile(): string
  {
    return $this->mobile;
  }
}

class Contractor extends Abstracts
{
  const TYPE_CUSTOMER = 0;

  public static function getById(int $resellerId): self
  {
    // выполняем какойто запрос к бд (возможно) что бы получить $reseller

    $reseller = 1;

    if ($reseller === null) {
      throw new \Exception('сlient not found!', 400);
    }

    return new self($resellerId); // fakes the getById method
  }
}

class Seller extends Abstracts
{
  public static function getById(int $resellerId): self
  {
    // выполняем какойто запрос к бд (возможно) что бы получить $reseller
    $reseller = 1;

    if ($reseller === null) {
      throw new \Exception('Seller not found!', 400);
    }

    return new self($resellerId);
  }
}

class Employee extends Abstracts
{
  public static function getById(int $resellerId): self
  {
    // выполняем какойто запрос к бд (возможно) что бы получить $reseller
    $reseller = 1;

    if ($reseller === null) {
      throw new \Exception('Creator not found!', 400);
    }

    return new self($resellerId);
  }
}

class Expert extends Abstracts
{
  public static function getById(int $resellerId): self
  {
    // выполняем какойто запрос к бд (возможно) что бы получить $reseller
    $reseller = 1;

    if ($reseller === null) {
      throw new \Exception('Expert not found!', 400);
    }

    return new self($resellerId);
  }
}

class Status
{
  public $id, $name;

  public static function getName(int | string $id): string
  {
    $a = [
      0 => 'Completed',
      1 => 'Pending',
      2 => 'Rejected',
    ];

    return $a[(int) $id];
  }
}

abstract class ReferencesOperation
{
  abstract public function doOperation(): array;

  public function getRequest($pName): array
  {
    return $_REQUEST[$pName];
  }
}

function getResellerEmailFrom()
{
  return 'contractor@example.com';
}

function getEmailsByPermit($resellerId, $event)
{
  // fakes the method
  return ['someemeil@example.com', 'someemeil2@example.com'];
}

class NotificationEvents
{
  const CHANGE_RETURN_STATUS = 'changeReturnStatus';
  const NEW_RETURN_STATUS = 'newReturnStatus';
}

class TsReturnOperation extends ReferencesOperation
{
  public const TYPE_NEW = 1;
  public const TYPE_CHANGE = 2;

  /**
   * @throws \Exception
   * @return array
   */
  public function doOperation(): array
  {
    $data = $this->getRequest('data');
    $resellerId = $data['resellerId'];
    $notificationType = (int) $data['notificationType'];
    $result = [
      'notificationEmployeeByEmail' => false,
      'notificationClientByEmail' => false,
      'notificationClientBySms' => [
        'isSent' => false,
        'message' => '',
      ],
    ];

    if (empty($resellerId)) {
      $result['notificationClientBySms']['message'] = 'Empty resellerId';
      return $result;
    }

    if (empty($notificationType)) {
      throw new \Exception('Empty notificationType', 400);
    }

    // В нашем случаии объект класса всегда будет создан если не возникнет ошибка
    Seller::getById((int) $resellerId);

    $client = Contractor::getById((int) $data['clientId']);

    if ($client->getType() !== Contractor::TYPE_CUSTOMER || $client->getId() !== $resellerId) {
      throw new \Exception('сlient not found!', 400);
    } 

    $cFullName = $client->getFullName();

    // Вообще не нужна, а если что то и понадобиться изменить то нужно в методе getFullName сделать
    // if (empty($client->getFullName())) {
    //   $cFullName = $client->name;
    // }

    $cr = Employee::getById((int) $data['creatorId']);

    $et = Expert::getById((int) $data['expertId']);

    $differences = '';
    if ($notificationType === self::TYPE_NEW) {
      $differences = __('NewPositionAdded', null, $resellerId);
    } elseif ($notificationType === self::TYPE_CHANGE && !empty($data['differences'])) {
      $differences = __('PositionStatusHasChanged', [
        'FROM' => Status::getName($data['differences']['from']),
        'TO' => Status::getName($data['differences']['to']),
      ], $resellerId);
    }

    $templateData = [
      'COMPLAINT_ID' => (int) $data['complaintId'],
      'COMPLAINT_NUMBER' => (string) $data['complaintNumber'],
      'CREATOR_ID' => (int) $data['creatorId'],
      'CREATOR_NAME' => $cr->getFullName(),
      'EXPERT_ID' => (int) $data['expertId'],
      'EXPERT_NAME' => $et->getFullName(),
      'CLIENT_ID' => (int) $data['clientId'],
      'CLIENT_NAME' => $cFullName,
      'CONSUMPTION_ID' => (int) $data['consumptionId'],
      'CONSUMPTION_NUMBER' => (string) $data['consumptionNumber'],
      'AGREEMENT_NUMBER' => (string) $data['agreementNumber'],
      'DATE' => (string) $data['date'],
      'DIFFERENCES' => $differences,
    ];
    
    // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
    foreach ($templateData as $key => $tempData) {
      if (empty($tempData)) {
        throw new \Exception("Template Data ({$key}) is empty!", 500);
      }
    }

    $emailFrom = getResellerEmailFrom();
    // Получаем email сотрудников из настроек
    $emails = getEmailsByPermit($resellerId, 'tsGoodsReturn');
    if (!empty($emailFrom) && sizeof($emails) > 0) {
      foreach ($emails as $email) {
        MessagesClient::sendMessage([
          0 => [ // MessageTypes::EMAIL
            'emailFrom' => $emailFrom,
            'emailTo' => $email,
            'subject' => __('complaintEmployeeEmailSubject', $templateData, $resellerId),
            'message' => __('complaintEmployeeEmailBody', $templateData, $resellerId),
          ],
        ], $resellerId, NotificationEvents::CHANGE_RETURN_STATUS);
        $result['notificationEmployeeByEmail'] = true;

      }
    }

    // Шлём клиентское уведомление, только если произошла смена статуса
    if ($notificationType === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
      if (!empty($emailFrom) && !empty($client->getEmail())) {
        MessagesClient::sendMessage([
          0 => [ // MessageTypes::EMAIL
            'emailFrom' => $emailFrom,
            'emailTo' => $client->getEmail(),
            'subject' => __('complaintClientEmailSubject', $templateData, $resellerId),
            'message' => __('complaintClientEmailBody', $templateData, $resellerId),
          ],
        ], $resellerId, $client->getId(), NotificationEvents::CHANGE_RETURN_STATUS, (int) $data['differences']['to']);
        $result['notificationClientByEmail'] = true;
      }

      if (!empty($client->getMobile())) {
        $error = '';

        $res = NotificationManager::send($resellerId, $client->getId(), NotificationEvents::CHANGE_RETURN_STATUS, (int) $data['differences']['to'], $templateData, $error);
        if ($res) {
          $result['notificationClientBySms']['isSent'] = true;
        }
        if (!empty($error)) {
          $result['notificationClientBySms']['message'] = $error;
        }
      }
    }

    return $result;
  }
}