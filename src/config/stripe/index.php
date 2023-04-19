<?php
use \Stripe\StripeClient;

// Cuenta Stripe Alejo en produccion
// secret key => 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
// public key => pk_live_eFuiI3NPMAPyFdOaAB5tb0MD00Lv4uavtE

// Cuenta Stripe Carlos Mino en pruebas
// secret key => sk_test_51IyjutJRG2JZWM61PyBb66fVzuPdn6YrqY9Wpn95ug31xFXFkZhre8GziBtaUCZ4I2Ie48ewCs0nlU9rFwaJXBbJ005nfW0jXD
// public key => pk_test_51IyjutJRG2JZWM61aKkQfsE8yIKbbuHXFCx7cIIipYnA9IDp3kzuUFiPhiyE0Qq17ZWa9cHMi69UUwrQFbo6J7QG0045F2JxnW

class Stripe{
    private $url = "https://api.stripe.com";
    private $key = "";
    private $stripeClient = null;    

    function __construct($ambiente = "test"){
        if ($ambiente == "test"){
            $this->key = "sk_test_51IyjutJRG2JZWM61PyBb66fVzuPdn6YrqY9Wpn95ug31xFXFkZhre8GziBtaUCZ4I2Ie48ewCs0nlU9rFwaJXBbJ005nfW0jXD";
        }else if ($ambiente == "live"){
            $this->key = "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";
        }
        $this->stripeClient = new StripeClient($this->key);
    }

    function getClients(){
        // return $this->stripeClient->customers->all();
        // return $this->stripeClient->balance->retrieve();

    }

    function setNewCustomer($data){
        try{
            return $this->stripeClient->customers->create($data);
        }catch(\Stripe\Exception\CardException $e){
            return $e->getError()->message;
        } catch (\Stripe\Exception\RateLimitException $e) {
            // Too many requests made to the API too quickly
            return $e->getError()->message;
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Invalid parameters were supplied to Stripe's API
            return $e->getError()->message;
        } catch (\Stripe\Exception\AuthenticationException $e) {
            // Authentication with Stripe's API failed
            // (maybe you changed API keys recently)
            return $e->getError()->message;
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            // Network communication with Stripe failed
            return $e->getError()->message;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Display a very generic error to the user, and maybe send
            // yourself an email
            return $e->getError()->message;
        } catch (Exception $e) {
            // Something else happened, completely unrelated to Stripe
            return $e->getMessage();
        }        
    }

    function UpdateCustomer($idUser, $data){        
        try{
            return $this->stripeClient->customers->update($idUser, $data);
        }catch(\Stripe\Exception\CardException $e){
            return $e->getError()->message;
        } catch (\Stripe\Exception\RateLimitException $e) {
            // Too many requests made to the API too quickly
            return $e->getError()->message;
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Invalid parameters were supplied to Stripe's API
            return $e->getError()->message;
        } catch (\Stripe\Exception\AuthenticationException $e) {
            // Authentication with Stripe's API failed
            // (maybe you changed API keys recently)
            return $e->getError()->message;
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            // Network communication with Stripe failed
            return $e->getError()->message;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Display a very generic error to the user, and maybe send
            // yourself an email
            return $e->getError()->message;
        } catch (Exception $e) {
            // Something else happened, completely unrelated to Stripe
            return $e->getMessage();
        }      
    }

    function TokenCard($creditcard){    
        try{
            return $this->stripeClient->tokens->create($creditcard);
        }catch(\Stripe\Exception\CardException $e){
            return $e->getError()->message;
        } catch (\Stripe\Exception\RateLimitException $e) {
            // Too many requests made to the API too quickly
            return $e->getError()->message;
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Invalid parameters were supplied to Stripe's API
            return $e->getError()->message;
        } catch (\Stripe\Exception\AuthenticationException $e) {
            // Authentication with Stripe's API failed
            // (maybe you changed API keys recently)
            return $e->getError()->message;
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            // Network communication with Stripe failed
            return $e->getError()->message;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Display a very generic error to the user, and maybe send
            // yourself an email
            return $e->getError()->message;
        } catch (Exception $e) {
            // Something else happened, completely unrelated to Stripe
            return $e->getMessage();
        }
    }

    function setNewCharge($data){           
        try{
            return $this->stripeClient->charges->create($data);
        }catch(\Stripe\Exception\CardException $e){
            return $e->getError()->message;
        } catch (\Stripe\Exception\RateLimitException $e) {
            // Too many requests made to the API too quickly
            return $e->getError()->message;
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Invalid parameters were supplied to Stripe's API
            return $e->getError()->message;
        } catch (\Stripe\Exception\AuthenticationException $e) {
            // Authentication with Stripe's API failed
            // (maybe you changed API keys recently)
            return $e->getError()->message;
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            // Network communication with Stripe failed
            return $e->getError()->message;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Display a very generic error to the user, and maybe send
            // yourself an email
            return $e->getError()->message;
        } catch (Exception $e) {
            // Something else happened, completely unrelated to Stripe
            return $e->getMessage();
        }        
    }

    function setRefunds($data){           
        try{
            return $this->stripeClient->refunds->create($data);
        }catch(\Stripe\Exception\CardException $e){
            return $e->getError()->message;
        } catch (\Stripe\Exception\RateLimitException $e) {
            // Too many requests made to the API too quickly
            return $e->getError()->message;
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Invalid parameters were supplied to Stripe's API
            return $e->getError()->message;
        } catch (\Stripe\Exception\AuthenticationException $e) {
            // Authentication with Stripe's API failed
            // (maybe you changed API keys recently)
            return $e->getError()->message;
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            // Network communication with Stripe failed
            return $e->getError()->message;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Display a very generic error to the user, and maybe send
            // yourself an email
            return $e->getError()->message;
        } catch (Exception $e) {
            // Something else happened, completely unrelated to Stripe
            return $e->getMessage();
        }        
    }
}