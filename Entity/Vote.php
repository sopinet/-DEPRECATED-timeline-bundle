<?php
// src/Trazeo\BaseBundle/Entity/Vote.php

namespace Sopinet\TimelineBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOS\CommentBundle\Entity\Vote as BaseVote;
use FOS\CommentBundle\Model\SignedVoteInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity
 * @ORM\ChangeTrackingPolicy("DEFERRED_EXPLICIT")
 */
class Vote extends BaseVote implements SignedVoteInterface
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\generatedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * Comment of this vote
     *
     * @var Comment
     * @ORM\ManyToOne(targetEntity="Sopinet\TimelineBundle\Entity\Comment")
     */
    protected $comment;
    
    /**
     * Author of the vote
     *
     * @ORM\ManyToOne(targetEntity="Application\Sonata\UserBundle\Entity\User")
     * @var User
     */
    protected $voter;
    
    /**
     * Sets the owner of the vote
     *
     * @param string $user
     */
    public function setVoter(UserInterface $voter)
    {
    	$this->voter = $voter;
    }
    
    /**
     * Gets the owner of the vote
     *
     * @return UserInterface
     */
    public function getVoter()
    {
    	return $this->voter;
    }
}
