<?php

namespace Pancake;

class Invoice {

    protected $internal_fields = [];
    protected $server;

    function __construct(Server $server) {
        $this->server = $server;
        $this->internal_fields['type'] = "DETAILED";
    }

    function __set($name, $value) {
        $this->internal_fields[$name] = $value;
    }

    function __get($name) {
        return $this->internal_fields[$name];
    }

    function __isset($name) {
        return isset($this->internal_fields[$name]);
    }

    function __unset($name) {
        unset($this->internal_fields[$name]);
    }

    function addPaymentPart($is_percentage, $amount, $due_date, $notes) {
        if (!isset($this->internal_fields['partial-amount'])) {
            $this->internal_fields['partial-is_percentage'] = [];
            $this->internal_fields['partial-amount'] = [];
            $this->internal_fields['partial-due_date'] = [];
            $this->internal_fields['partial-notes'] = [];
        }

        $this->internal_fields['partial-is_percentage'][] = $is_percentage;
        $this->internal_fields['partial-amount'][] = $amount;
        $this->internal_fields['partial-due_date'][] = $due_date;
        $this->internal_fields['partial-notes'][] = $notes;
    }

    function addPercentagePaymentPart($amount, $due_date, $notes) {
        $this->addPaymentPart(true, $amount, $due_date, $notes);
    }

    function addFixedPaymentPart($amount, $due_date, $notes) {
        $this->addPaymentPart(false, $amount, $due_date, $notes);
    }

    function addLineItem($type, $name, $qty, $rate, $tax_ids, $description, $discount) {
        if (!isset($this->internal_fields['items'])) {
            $this->internal_fields['items'] = [];
        }

        $total = $qty * $rate;

        if (stristr($discount, "%") !== false) {
            $discount_is_percentage = 1;
            $discount = str_ireplace("%", "", $discount);
        } else {
            $discount_is_percentage = 0;
        }

        # Remove item discount from item total.
        if ($discount_is_percentage) {
            $total = $total - ($discount * $total / 100);
        } else {
            $total = $total - $discount;
        }

        if (in_array($type, array("fixed_discount", "percentage_discount"))) {
            $total = 0;
        }

        $this->internal_fields['items'][] = [
            'name' => $name,
            'description' => $description,
            'qty' => $qty,
            'rate' => $rate,
            'item_time_entries' => "",
            'item_type_table' => "",
            'item_type_id' => 0,
            'tax_ids' => $tax_ids,
            'discount' => $discount,
            'discount_is_percentage' => $discount_is_percentage,
            'total' => $qty * $rate,
            'sort' => (count($this->internal_fields['items']) + 1),
            'type' => $type
        ];
    }

    function addStandardLineItem($name, $qty, $rate, $taxes, $description, $discount) {
        $this->addLineItem("standard", $name, $qty, $rate, $taxes, $description, $discount);
    }

    function addFlatRateLineItem($name, $rate, $taxes, $description, $discount) {
        $this->addLineItem("flat_rate", $name, 1, $rate, $taxes, $description, $discount);
    }

    function addFixedDiscountLineItem($name, $discount) {
        $this->addLineItem("fixed_discount", $name, 1, 0, [], "", $discount);
    }

    function addPercentageDiscountLineItem($name, $discount) {
        $this->addLineItem("percentage_discount", $name, 1, 0, [], "", $discount);
    }

    function addFile($url) {
        if (!isset($this->internal_fields['files'])) {
            $this->internal_fields['files'] = [];
        }

        if (substr($url, 0, strlen("http")) == "http") {
            $http = new \HTTP_Request();
            $contents = $http->request($url);
            $url = explode("?", $url);
            $url = explode("/", $url[0]);
            $filename = end($url);
        } else {
            $contents = file_get_contents($url);
            $filename = pathinfo($url, PATHINFO_BASENAME);
        }

        $this->internal_fields['files'][] = ["filename" => $filename, "contents" => base64_encode($contents)];
    }

    function addFileFromContents($contents, $filename) {
        if (!isset($this->internal_fields['files'])) {
            $this->internal_fields['files'] = [];
        }

        $this->internal_fields['files'][] = ["filename" => $filename, "contents" => base64_encode($contents)];
    }

    function save() {
        return $this->server->post("invoices/advanced_create", $this->internal_fields);
    }

}
