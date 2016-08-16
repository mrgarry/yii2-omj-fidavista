<?php

namespace omj\financetools\fidavista;

use omj\financetools\statementstandart\iStatement;
use omj\financetools\statementstandart\iStatementAccount;
use omj\financetools\statementstandart\iStatementCurrency;
use omj\financetools\statementstandart\iStatementTransaction;

/**
 * This class parses the account statement from XML node
 */

class FidavistaAccount implements iStatementAccount {
    
    private $acctName = 'NULL';
    
    private $ccyList = [];
    
    function __construct(\DOMNode $accountSet) {
        
        foreach ($accountSet->childNodes as $node) {
            
            switch ($node->nodeName) {
                case 'AccNo':
                    $this->acctName = $node->nodeValue;
                    break;
                case 'CcyStmt':
                    $this->ccyList[] = new FidavistaCurrency($node);
                    break;
            }
        }
    }
    
    function __destruct() {
        foreach ($this->ccyList as $ccy) {
            unset($ccy);
        }
        unset($this->ccyList);
    }
    
    public function addCurrency(iStatementCurrency $ccy) {
        //this feature is disabled in this class
    }
    
    /**
     * Returns the list of FidavistaCurrency objects
     * @return FidavistaCurrency
     */
    public function getCurrencies() {
        return $this->ccyList;
    }
    
    /**
     * Returns the name of the account
     * @return string
     */
    public function name() {
        return $this->acctName;
    }
    
}