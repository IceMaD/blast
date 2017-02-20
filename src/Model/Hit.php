<?php

namespace Model;

use SimpleXMLElement;

class Hit
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $protein;

    /**
     * @var string
     */
    private $reference;

    /**
     * @var string
     */
    private $accession;

    /**
     * @var int
     */
    private $length;

    /**
     * @var SimpleXMLElement
     */
    private $hsp;

    /**
     * @var float
     */
    private $identity;

    public function __construct(\SimpleXMLElement $hit)
    {
        $this->id = $hit->Hit_id->__toString();
        $this->reference = $hit->Hit_def->__toString();
        $this->accession = $hit->Hit_accession->__toString();
        $this->length = (int) $hit->Hit_len->__toString();
        $this->hsp = $hit->Hit_hsps->Hsp;
        $this->identity = round((int) $this->hsp->{'Hsp_identity'} * 100 / (int) $this->hsp->{'Hsp_align-len'});
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $protein
     *
     * @return Hit
     */
    public function setProtein($protein)
    {
        $this->protein = $protein;

        return $this;
    }

    /**
     * @return string
     */
    public function getProtein()
    {
        return $this->protein;
    }

    /**
     * @return string
     */
    public function getReference()
    {
        return $this->reference;
    }

    /**
     * @return string
     */
    public function getAccession()
    {
        return $this->accession;
    }

    /**
     * @return int
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * @return SimpleXMLElement
     */
    public function getHsp()
    {
        return $this->hsp;
    }

    /**
     * @return float
     */
    public function getIdentity()
    {
        return $this->identity;
    }
}
