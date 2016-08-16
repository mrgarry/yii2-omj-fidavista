<?php

/* 
 * Strādājam ar FidaVista standarta konta atskaiti
 * Princips vienkāršs - ielādē XML failu kā DOMDocument objektu
 * un tālāk izdod info pa ierakstiem
 */
namespace omj\financetools\fidavista;

use omj\financetools\statementstandart\iStatement;
use omj\financetools\statementstandart\iStatementAccount;
use omj\financetools\statementstandart\iStatementCurrency;
use omj\financetools\statementstandart\iStatementTransaction;
use omj\financetools\statementstandart\iAccountStatementDbController;

/**
 * Parses the XML bank statement and creates 
 * object structure for export to iAccountStatementDbController
 */

class FidavistaStatement implements iStatement {

    /**
     * @var \DOMNode
     */
    private $xmlNode;        //DOMNode;
    
    /**
     * @var iAccountStatementDbController 
     */
    private $dbInterface;    //iAccountStatementDbController;
    //header data
    private $timestamp = ''; //eg, 20150823134808668 (2015-08-23 13:48 08.668)
    private $from = '';      //statement provider (bank info)
    //stmt dates
    private $startDate;
    private $endDate;
    private $prepDate;
    //bank data
    private $bankName;
    private $bankId;
    private $bankAddress;
    //beneficiary data
    private $beneficiaryName;
    private $beneficiaryId;
    // account list and statement data
    private $accountList = [];

    private function addHeaderData(\DOMNode $node) {

        if ($node->nodeName == 'Header') {
            foreach ($node->childNodes as $child) {
                switch ($child->nodeName) {
                    case 'Timestamp':
                        $this->timestamp = $child->nodeValue;
                        break;
                    case 'From':
                        $this->from = $child->nodeValue;
                        break;
                }
            }
        }
    }
   
    private function addPeriod(\DOMNode $node) {
        if ($node->nodeName == 'Period') {
            foreach ($node->childNodes as $child) {
                switch ($child->nodeName) {
                    case 'StartDate':
                        $this->startDate = $child->nodeValue;
                        break;
                    case 'EndDate':
                        $this->endDate = $child->nodeValue;
                        break;
                    case 'PrepDate':
                        $this->prepDate = $child->nodeValue;
                        break;
                }
            }
        }
    }
    
    private function addBankData(\DOMNode $node) {
        if ($node->nodeName == 'BankSet') {
            foreach ($node->childNodes as $child) {
                switch ($child->nodeName) {
                    case 'Name':
                        $this->bankName = $child->nodeValue;
                        break;
                    case 'LegalId':
                        $this->bankId = $child->nodeValue;
                        break;
                    case 'Address':
                        $this->bankAddress = $child->nodeValue;
                        break;
                }
            }
        }
    }
    
    private function addBeneficiaryData(\DOMNode $node) {
        if ($node->nodeName == 'ClientSet') {
            foreach ($node->childNodes as $child) {
                switch ($child->nodeName) {
                    case 'Name':
                        $this->beneficiaryName = $child->nodeValue;
                        break;
                    case 'LegalId':
                        $this->beneficiaryId = $child->nodeValue;
                        break;
                }
            }
        }
    }
    
    private function getStatementRow(FidavistaTransaction $trx, $currencyName, $accountName) {
        
        $row = [];
        $row['UniqueIdCheck'] = $trx->bankReference() . '-' . $trx->paymentAmt();
        $row['PmtBankRef'] = $trx->bankReference();
        $row['PmtDate'] = $trx->paymentDate();
        $row['PmtAmount'] = $trx->paymentAmt();
        $row['PmtCurrency'] = $currencyName;
        $row['CorD'] = $trx->CorD();
        $row['PmtInfo'] = $trx->paymentInfo();
        $row['PayeeName'] = $trx->payeeName();
        $row['PayeeLegalID'] = $trx->payeeCode();
        $row['PayeeAccount'] = $trx->payeeAccount();
        $row['PayeeBankName'] = '';
        $row['PayeeBankSwift'] = $trx->payeeBankCode();
        $row['BeneficiaryName'] = $this->beneficiaryName;
        $row['BeneficiaryLegalID'] = $this->beneficiaryId;
        $row['BeneficiaryAccount'] = $accountName;
        $row['BeneficiaryBankName'] = $this->bankName;
        $row['BeneficiaryBankLegalID'] = $this->bankId;
        $row['Processed'] = 0;
        return $row;       
    }
    
    function __construct($xmlString, iAccountStatementDbController $dbController) {
        
        $domDocument = new \DOMDocument();
        $domDocument->loadXML($xmlString);
        
        $this->dbInterface = $dbController;
        
        $this->xmlNode = $domDocument->childNodes->item(0);
        
        if ($this->xmlNode->hasChildNodes() && ($this->xmlNode->childNodes->length == 2) ) {
            $node = $this->xmlNode->childNodes->item(0);
            if ($node->nodeName == 'Header') {
                $this->addHeaderData($node);
            }
            
            $node = $this->xmlNode->childNodes->item(1);
            foreach ($node->childNodes as $child) {
                switch ($child->nodeName) {
                    case 'Period':
                        $this->addPeriod($child);
                        break;
                    case 'BankSet':
                        $this->addBankData($child);
                        break;
                    case 'ClientSet':
                        $this->addBeneficiaryData($child);
                        break;
                    case 'AccountSet':
                        $acct = new FidavistaAccount($child);
                        $this->accountList[] = $acct;
                        break;
                }
            }
        }
        else {
            throw new \yii\console\Exception('Invalid FIDAVISTA provided. Missing Header or other parts');
        }
        unset($domDocument);
        
        $this->dbInterface->setData($this->getStatement());
    }
    
    function __destruct() {
        foreach ($this->accountList as $account) {
            unset($account);
        }
        unset($this->xmlNode, $this->accountList, $this->dbInterface);
    }
    
    public function addAccount(iStatementAccount $acct) {
        //this feature is disabled for fidavista
    } //disabled

    public function getAccounts() {
        return $this->accountList;
    }
    
    /**
     * Returns 2-dim array with bank statement data ready to be imported into any DB
     * atgriež smuku 2-dimensiju masīvu ar statement data, kas ir gatavs importam uz DB
     * @return array
     */
    public function getStatement() {
        
        $response = [];
        
        //iegūst visus datus un sakārto tos smukā masīvā
        foreach ($this->accountList as $account) {
            $currencyList = $account->getCurrencies();
            $accountName = $account->name();
            
            foreach ($currencyList as $currency) {
                $trxList = $currency->getTransactions();
                $currencyName = $currency->name();

                foreach ($trxList as $trx) {
                    //sākam paša masīva formēšanu
                    $response[] = $this->getStatementRow($trx, $currencyName, $accountName);
                }
            }
        }
        return $response;
    }
    
    /**
     * Checks if given file data is valid FidaVista/XML bank statement
     * @param string $statement file contents that need to be checked
     * @return boolean
     */
    
    public static function isFidaVista($statement) {

        $result = false;
        $domDocument = new \DOMDocument();
        libxml_use_internal_errors(true);
        
        try {
            //$domDocument->loadXML($statement);
            if ($domDocument->loadXML($statement) == true) {
                $node = $domDocument->childNodes->item(0);
                
                if ($node->nodeName === 'FIDAVISTA') {
                    $result = true;
                }
            }
        } catch (Exception $exc) {
            $result = false;
        } finally {
            unset($domDocument);
        }
        
        return $result;
    }
    
    /**
     * Saves the data to DB using iAccountStatementDbController
     * @param bool $incomingOnly
     * @return int Optinally can be implemented in iAccountStatementDbController - num of rows imported
     */
    public function saveToDb($incomingOnly = false) {
        $count = ($incomingOnly) ? $this->dbInterface->executeDataImportIncoming() : $this->dbInterface->executeDataImport();
        return $count;
    }
}