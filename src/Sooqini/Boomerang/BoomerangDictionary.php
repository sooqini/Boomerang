<?php

namespace Sooqini\Boomerang;

require_once 'SpinTrait.php';

use Johnsn\GuerrillaMail\GuerrillaMail;
use Johnsn\GuerrillaMail\GuerrillaConnect\CurlConnection;
use Nunzion\Expect;
use Behat\Gherkin\Node\TableNode;
use Behat\Gherkin\Node\StepNode;

const GM_DOMAIN = '@guerrillamailblock.com';
const FB_DOMAIN = '@tfbnw.net';

trait BoomerangDictionary
{
    use SpinTrait;

    private $service;

    private $emails;
    private $accounts;
    private $connection;

    public function setBoomerangServiceProvider(ServiceProvider $service) {
        $this->service = $service;
    }


    /**
     * @BeforeScenario @mockEmail
     */
    public function enableMockEmails()
    {
        $this->connection = new CurlConnection("127.0.0.1");
        if(substr_compare(@$_SERVER['ADMIN_EMAIL'], GM_DOMAIN, -strlen(GM_DOMAIN)) === 0) {
            $account = new GuerrillaMail($this->connection);
            $account->get_email_address();
            $account->set_email_address(substr($_SERVER['ADMIN_EMAIL'], 0, -strlen(GM_DOMAIN)));
            $account->get_email_list(); //hack to get GuerrillaMail to wake up
            $inbox = $account->get_email_list();
            $account->del_email(array_map(function($message) {
                return $message['mail_id'];
            }, $inbox['list']));
            $this->accounts[$_SERVER['ADMIN_EMAIL']] = $account;
        }
        //$event->getScenario()->addStep(new StepNode('And', 'no other emails should of been sent'));
    }

     /**
     * @Transform /^([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4})$/
     */
    public function mockEmail($string) {
        if($this->connection && substr_compare($string, FB_DOMAIN, -strlen(FB_DOMAIN)) !== 0) {
            $email = @$this->emails[$string];
            if(!$email) {
                if(isset($this->accounts[$string])) {
                    $this->emails[$string] = $string;
                    return $string;
                }
                $account = new GuerrillaMail($this->connection);
                $address = $account->get_email_address();
                $email = $address['email_addr'];
                $this->emails[$string] = $email;
                $this->accounts[$email] = $account;
            }
            $string = $email;
        }
        return $string;
    }

    /**
     * @Transform /^table:/
     */
    public function mockEmailInTable(TableNode $table) {
        if($this->connection) {
            $rows = $table->getRows();
            foreach($rows as $index => $row) {
                foreach($row as $key => $value) {
                    if(preg_match('/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i', $value)) {
                        $row[$key] = $this->mockEmail($value);
                    }
                    $rows[$index] = $row;
                }
            }
            $table->setRows($rows);
        }
        return $table;
    }

    /**
     * @Given /^no (?:|other )emails should of been sent$/
     */
    public function checkForUnexpectedEmails()
    {
        Expect::that($this->connection)->isNotNull();
        foreach($this->accounts as $email => $account) {
            $inbox = $account->get_email_list();
            foreach(array_slice($inbox['list'], 0, (int)@$inbox['count']) as $message) {
                if(!$message['mail_read']) {
                    throw new Exception("Unread mail with the subject '{$message['mail_subject']}' received at $email");
                }
            }
        }
    }

    private function accountForEmail($email = null)
    {
        if($email) {
            $email = $this->accounts[$email];
        } else {
            Expect::that(count($this->emails))->equals(1);
            $email = $this->accounts[current($this->emails)];
        }
        return $email;
    }

    private function getNewMessage($account)
    {
        $this->spin(function($context) use($account, &$inbox) {
            $inbox = $account->check_email();
            return @$inbox['count'] && !$inbox['list'][0]['mail_read'];
        }, 5);
        $message = $inbox['list'][0];
        $message['mail_subject'] = html_entity_decode(htmlspecialchars_decode($message['mail_subject'], ENT_QUOTES|ENT_HTML5), ENT_QUOTES|ENT_HTML5);
        return $message;
    }

    /**
     * @Then /^(?:|I |I should )receive an email (?:|at (\S*) )with the subject "([^"]*)"$/
     */
    public function iShouldReceiveAnEmailWithTheSubject($email, $subject)
    {
        Expect::that($this->connection)->isNotNull();
        $account = $this->accountForEmail($email);
        $message = $this->getNewMessage($account);
        Expect::that($message['mail_subject'])->equals($subject);
        $account->fetch_email($message['mail_id']);
    }

    /**
     * @Given /^(?:|I )click "([^"]*)" in the email received(?:| at (\S*))$/
     */
    public function clickInTheEmail($link, $email = null)
    {
        Expect::that($this->connection)->isNotNull();
        $account = $this->accountForEmail($email);
        $message = $this->getNewMessage($account);
        $message = $account->fetch_email($message['mail_id']);
        $dom = new DomDocument;
        $dom->loadHTML($message['mail_body']);
        $xpath = new DomXPath($dom);
        $link = $this->getSession()->getSelectorsHandler()->xpathLiteral($link);
        $list = $xpath->query($this->getSession()->getSelectorsHandler()->getSelector("named")->translateToXPath(array("link_or_button", $link)));
        if(!$list->length) {
            $list = $xpath->query($this->getSession()->getSelectorsHandler()->getSelector("named")->translateToXPath(array("link_or_button_parent", $link)));
        }
        Expect::that($list->length)->isGreaterThan(0);
        $link = $list->item($list->length-1)->getAttribute('href');
        $this->getSession()->visit($link);
    }
}