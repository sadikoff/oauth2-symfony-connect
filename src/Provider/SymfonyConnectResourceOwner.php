<?php

namespace Qdequippe\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class SymfonyConnectResourceOwner implements ResourceOwnerInterface
{
    /**
     * @var \DOMElement
     */
    private $data;

    /**
     * @var \DOMXpath
     */
    private $xpath;

    public function __construct($response)
    {
        $dom = new \DOMDocument();
        if (!$dom->loadXML($response)) {
            throw new \InvalidArgumentException('Could not transform this xml to a \DOMDocument instance.');
        }

        $this->xpath = new \DOMXpath($dom);

        $nodes = $this->xpath->evaluate('/api/root');
        $user = $this->xpath->query('./foaf:Person', $nodes->item(0));

        if (1 !== $user->length) {
            throw new \InvalidArgumentException('Could not retrieve valid user info.');
        }

        $this->data = $user->item(0);
    }

    public function getId()
    {
        return $this->data->attributes->getNamedItem('id')->value;
    }

    public function getName()
    {
        $username = null;
        $accounts = $this->xpath->query('./foaf:account/foaf:OnlineAccount', $this->data);
        for ($i = 0; $i < $accounts->length; ++$i) {
            $account = $accounts->item($i);
            if ('SensioLabs Connect' === $this->getNodeValue('./foaf:name', $account)) {
                $username = $this->getNodeValue('foaf:accountName', $account);

                break;
            }
        }

        return $username ?: $this->getNodeValue('./foaf:name', $this->data);
    }

    public function getProfilePicture()
    {
        return $this->getNodeValue('./atom:link[@rel="foaf:depiction"]', $this->data, 'link');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'profilePicture' => $this->getProfilePicture(),
        ];
    }

    protected function getNodeValue($query, \DOMElement $element = null, $index = 0)
    {
        $nodeList = $this->xpath->query($query, $element);
        if ($nodeList->length > 0 && $index <= $nodeList->length) {
            return $this->sanitizeValue($nodeList->item($index)->nodeValue);
        }
    }

    protected function sanitizeValue($value)
    {
        if ('true' === $value) {
            return true;
        }

        if ('false' === $value) {
            return false;
        }

        if (empty($value)) {
            return null;
        }

        return $value;
    }
}