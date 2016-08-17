<?php

namespace omj\financetools\fidavista;

use omj\financetools\statementstandart\iStatement;
use omj\financetools\statementstandart\iStatementAccount;
use omj\financetools\statementstandart\iStatementCurrency;
use omj\financetools\statementstandart\iStatementTransaction;

class FidavistaCurrency implements iStatementCurrency {
    
    private $transactionList = [];
    
    private $currencyName = 'N/A';
    
    private $openBal = 0;
    
    private $closeBal = 0;
    
    function __construct(\DOMNode $ccyStmt) {

        foreach ($ccyStmt->childNodes as $node) {
            switch ($node->nodeName) {
                case 'Ccy':
                    $this->currencyName = $node->nodeValue;
                    break;
                case 'OpenBal':
                    $this->openBal = $node->nodeValue;
                    break;
                case 'CloseBal':
                    $this->closeBal = $node->nodeValue;
                    break;
                case 'TrxSet':
                    $this->transactionList[] = new FidavistaTransaction($node);
                    break;
            }
        }
    }
    
    function __destruct() {
        foreach ($this->transactionList as $trx) {
            unset($trx);
        }
        unset($this->transactionList);
    }
    
    public function addTransaction(iStatementTransaction $trx) {
        //do nothing
    } //do not use this here
    
    /**
     * Returns the closing balance of the current currency in current account
     * @return string
     */
    public function closeBalance() {
        return $this->closeBal;
    }
    
    /**
     * Returns the array of FidavistaTransaction objects
     * (ie, transaction list in the current currency and current account)
     * @return array
     */
    public function getTransactions() {
        return $this->transactionList;
    }

    /**
     * Returns the name of the currency (ISO)
     * @return string
     */
    public function name() {
        return $this->currencyName;
    }
    
    /**
     * Returns the opening balance of the current currency in current account
     * @return string
     */
    public function openBalance() {
        return $this->openBal;
    }
    
}
