<?php

namespace Padosoft\AffiliateNetwork\Networks;

use Padosoft\AffiliateNetwork\Transaction;
use Padosoft\AffiliateNetwork\Merchant;
use Padosoft\AffiliateNetwork\Stat;
use Padosoft\AffiliateNetwork\Deal;
use Padosoft\AffiliateNetwork\AbstractNetwork;
use Padosoft\AffiliateNetwork\NetworkInterface;
use Padosoft\AffiliateNetwork\DealsResultset;
use Padosoft\AffiliateNetwork\ProductsResultset;

// require "../vendor/fubralimited/php-oara/Oara/Network/Publisher/Publicideas/Zapi/ApiClient.php";

/**
 * Class Publicideas
 * @package Padosoft\AffiliateNetwork\Networks
 */
class Publicideas extends AbstractNetwork implements NetworkInterface
{
    /**
     * @var object
     */
    private $_network = null;
    private $_username = '';
    private $_password = '';
    private $_token = '';
    private $_partner_id = '';
    private $_logged    = false;
    protected $_tracking_parameter    = 'cb';
    /**
     * @method __construct
     */
    public function __construct(string $username, string $password, string $token='',string $partner_id='')
    {
        $this->_network = new \Oara\Network\Publisher\Publicidees;
        $this->_username = $username;
        $this->_password = $password;
        $this->_token = $token;
        $this->_partner_id = $partner_id;
        $this->login( $this->_username, $this->_password );
    }

    public function login(string $username, string $password,string $idSite=''): bool{
        $this->_logged = false;
        if (isNullOrEmpty( $username ) || isNullOrEmpty( $password )) {

            return false;
        }
        $this->_username = $username;
        $this->_password = $password;
        $credentials = array();
        $credentials["user"] = $this->_username;
        $credentials["password"] = $this->_password;
        $this->_network->login($credentials);
        if ($this->_network->checkConnection()) {
            $this->_logged = true;
        }
        return $this->_logged;
    }

    /**
     * @return bool
     */
    public function checkLogin() : bool
    {
        return $this->_logged;
    }

    /**
     * @return array of Merchants
     */
    public function getMerchants() : array
    {
        $arrResult = array();
        $merchantList = $this->_network->getMerchantList();
        foreach($merchantList as $merchant) {
            $Merchant = Merchant::createInstance();
            $Merchant->merchant_ID = $merchant['cid'];
            $Merchant->name = $merchant['name'];
            $arrResult[] = $Merchant;
        }

        return $arrResult;
    }

    /**
     * @param int $merchantID
     * @return array of Deal
     */
    public function getDeals($merchantID=NULL,int $page=0,int $items_per_page=10 ): DealsResultset
    {
        $url = 'http://publisher.publicideas.com/xmlProgAff.php?partid='.$this->_partner_id.'&key='.$this->_token.'&noDownload=yes';
        $xml = file_get_contents($url);
        $arrResult = array();
        $arrResponse = xml2array($xml);
        if(!is_array($arrResponse) || count($arrResponse) <= 0) {
            return $arrResult;
        }
        $arrPartner = $arrResponse['partner'];

        /*
        foreach($arrPartner as $partner) {
            $Deal = Deal::createInstance();
            $Deal->program_name;
            if($merchantID > 0) {
                if($merchantID == $admediumItems['program']['@id']) {
                    $arrResult[] = $Deal;
                }
            }
            else {
                $arrResult[] = $Deal;
            }
        }
        */



        /*
        $this->_apiClient->setConnectId($this->_username);
        $this->_apiClient->setSecretKey($this->_password);
        $arrResponse = json_decode($this->_apiClient->getAdmedia(), true);
        $arrAdmediumItems = $arrResponse['admediumItems']['admediumItem'];
        $arrResult = array();
        foreach($arrAdmediumItems as $admediumItems) {
            $Deal = Deal::createInstance();
            $Deal->deal_ID = (int)$admediumItems['@id'];
            $Deal->name = $admediumItems['name'];
            $Deal->deal_type = $admediumItems['admediumType'];
            $Deal->merchant_ID = (int)$admediumItems['program']['@id'];
            $Deal->ppv = $admediumItems['trackingLinks']['trackingLink'][0]['ppv'];
            $Deal->ppc = $admediumItems['trackingLinks']['trackingLink'][0]['ppc'];
            if($merchantID > 0) {
                if($merchantID == $admediumItems['program']['@id']) {
                    $arrResult[] = $Deal;
                }
            }
            else {
                $arrResult[] = $Deal;
            }
        }

        return $arrResult;
        */

        return array();

    }

    /**
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     * @param array $arrMerchantID
     * @return array of Transaction
     * @throws \Exception
     */
    public function getSales(\DateTime $dateFrom, \DateTime $dateTo, array $arrMerchantID = array()) : array
    {
        try {
            if (!$this->checkLogin()) {
                return array();
            }
            $arrResult = array();
            if (count( $arrMerchantID ) < 1) {
                $merchants = $this->getMerchants();
                foreach ($merchants as $merchant) {
                    $arrMerchantID[$merchant->merchant_ID] = ['cid' => $merchant->merchant_ID, 'name' => $merchant->name];
                }
            }
            $transactionList = $this->_network->getTransactionList($arrMerchantID, $dateFrom, $dateTo);
            //$i=0;
            foreach($transactionList as $transaction) {
                try {
                    /*
                    $i++;
                    if ($i<6)
                        echo "transaction[date]: ".$transaction['date']."<br>";
                    */
                    $myTransaction = Transaction::createInstance();
                    $myTransaction->currency = $transaction['currency'];
                    $myTransaction->status = $transaction['status'];
                    $myTransaction->amount = $transaction['amount'];
                    $myTransaction->custom_ID = $transaction['custom_id'];
                    $myTransaction->title = $transaction['title'];
                    $myTransaction->unique_ID = $transaction['unique_id'];
                    $myTransaction->commission_ID = $transaction['commission_id'];
                    $myTransaction->commission = $transaction['commission'];
                    if (!empty($transaction['date'])) {
                        $date = new \DateTime($transaction['date']);
                        $myTransaction->date = $date;
                    }
                    if (!empty($transaction['validation_date'])) {
                        $date = new \DateTime($transaction['validation_date']);
                        $myTransaction->update_date = $date;
                    }
                    $myTransaction->merchant_ID = $transaction['merchantId'];
                    $myTransaction->approved = $transaction['approved'];
                    $arrResult[] = $myTransaction;
                } catch (\Exception $e) {
                    //echo "stepE ";
                    echo "<br><br>errore transazione Publicideas, id: ".$myTransaction->unique_ID." msg: ".$e->getMessage()."<br><br>";
                    //var_dump($e->getTraceAsString());
                }
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        return $arrResult;
    }

    /**
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     * @param int $merchantID
     * @return array of Stat
     */
    public function getStats(\DateTime $dateFrom, \DateTime $dateTo, int $merchantID = 0) : array
    {
        return array();
        /*
        $this->_apiClient->setConnectId($this->_username);
        $this->_apiClient->setSecretKey($this->_password);
        $dateFromIsoEngFormat = $dateFrom->format('Y-m-d');
        $dateToIsoEngFormat = $dateTo->format('Y-m-d');
        $response = $this->_apiClient->getReportBasic($dateFromIsoEngFormat, $dateToIsoEngFormat);
        $arrResponse = json_decode($response, true);
        $reportItems = $arrResponse['reportItems'];
        $Stat = Stat::createInstance();
        $Stat->reportItems = $reportItems;

        return array($Stat);
        */
    }


    /**
     * @param  array $params
     *
     * @return ProductsResultset
     */
    public function getProducts(array $params = []): ProductsResultset
    {
        // TODO: Implement getProducts() method.
        throw new \Exception("Not implemented yet");
    }

    public function getTrackingParameter(){
        return $this->_tracking_parameter;
    }
}
