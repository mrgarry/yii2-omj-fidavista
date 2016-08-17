<?php

namespace omj\financetools\fidavista;

use omj\financetools\statementstandart\iStatement;
use omj\financetools\statementstandart\iStatementAccount;
use omj\financetools\statementstandart\iStatementCurrency;
use omj\financetools\statementstandart\iStatementTransaction;


class FidavistaTransaction implements iStatementTransaction {
    
    private $data = []; // array
    
    private function populateData(\DOMNode $node) {
        
        $response = false;
        foreach ($node->childNodes as $child) {
            if ($child->hasChildNodes()) {
                $response[$child->nodeName] = $this->populateData($child);
            }
            else {
                if ($child->nodeName == '#text') {
                    $response = $child->textContent;
                }
                else {
                    $response[$child->nodeName] = $child->textContent;
                }
            }
        }
        
        return $response;
    }
    
    function __construct(\DOMNode $trx) {
        
        foreach ($trx->childNodes as $child) {
            $this->data[$child->nodeName] = $this->populateData($child);
        }
        
    }
    
    function __destruct() {
        unset($this->data);
    }
    
    public function bankReference() {
        return $this->data['BankRef'];
    }
    
    public function CorD() {
        return $this->data['CorD'];
    }
    
    public function payeeAccount() {
        $resp = (isset($this->data['CPartySet']['AccNo'])) ? $this->data['CPartySet']['AccNo'] : 'NULL';
        return $resp; //null if it's a bank fee; the same about 3 lower routines
    }
    
    public function payeeBankCode() {
        $resp = (isset($this->data['CPartySet']['BankCode'])) ? $this->data['CPartySet']['BankCode'] : 'NULL';
        return $resp;
    }
    
    public function payeeName() {
        $resp = (isset($this->data['CPartySet']['AccHolder']['Name'])) ? $this->data['CPartySet']['AccHolder']['Name'] : 'BANK SERVICE';
        return $resp;
    }
    
    public function payeeCode() {
        $resp = (isset($this->data['CPartySet']['AccHolder']['LegalId'])) ? $this->data['CPartySet']['AccHolder']['LegalId'] : 'NULL';
        return $resp;
    }
    
    public function paymentAmt() {
        return $this->data['AccAmt'];
    }
    
    public function paymentDate() {
        return $this->data['ValueDate'];
    }
    
    public function paymentInfo() {
        return $this->data['PmtInfo'];
    }

}

