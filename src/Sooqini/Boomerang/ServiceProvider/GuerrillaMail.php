<?php

namespace Sooqini\Boomerang\ServiceProvider;

use Sooqini\Boomerang\ServiceProvider;

class GuerrillaMail implements ServiceProvider {

    public function __construct(array $includedUsernames, array $exlcudedDomains) {
        $this->connection = new CurlConnection("127.0.0.1");
        foreach($includedUsernames as $username) {
            $account = new Johnsn\GuerrillaMail\GuerrillaMail($this->connection);
            $account->get_email_address();
            $account->set_email_address($username);
            $account->get_email_list(); //hack to get GuerrillaMail to wake up
            $inbox = $account->get_email_list();
            $account->del_email(array_map(function($message) {
                return $message['mail_id'];
            }, $inbox['list']));
            $this->accounts[$_SERVER['ADMIN_EMAIL']] = $account;
        }
    }

}