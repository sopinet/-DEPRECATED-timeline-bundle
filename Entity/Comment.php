<?php
// src/MyProject/MyBundle/Entity/Comment.php

namespace Sopinet\TimelineBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOS\CommentBundle\Entity\Comment as BaseComment;
use FOS\CommentBundle\Model\SignedCommentInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use FOS\CommentBundle\Model\VotableCommentInterface;
use JMS\Serializer\Annotation\Exclude;

/**
 * @ORM\Entity
 * @ORM\ChangeTrackingPolicy("DEFERRED_EXPLICIT")
 */
class Comment extends BaseComment implements SignedCommentInterface, VotableCommentInterface
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * Thread of this comment
     *
     * @var Thread
     * @ORM\ManyToOne(targetEntity="Sopinet\TimelineBundle\Entity\Thread")
     */
    protected $thread;
    
    /**
     * Author of the comment
     *
     * @ORM\ManyToOne(targetEntity="Application\Sonata\UserBundle\Entity\User")
     * @var User
     * @Exclude
     */
    protected $author;
    
    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $score = 0;
    
    protected $authorName;
    
    /**
     * Sets the score of the comment.
     *
     * @param integer $score
     */
    public function setScore($score)
    {
    	$this->score = $score;
    }
    
    /**
     * Returns the current score of the comment.
     *
     * @return integer
     */
    public function getScore()
    {
    	return $this->score;
    }
    
    /**
     * Increments the comment score by the provided
     * value.
     *
     * @param integer value
     *
     * @return integer The new comment score
     */
    public function incrementScore($by = 1)
    {
    	$this->score += $by;
    }
    
    public function setAuthor(UserInterface $author)
    {
    	$this->author = $author;
    }
    
    public function getAuthor()
    {
    	return $this->author;
    }
    
    public function setAuthorName($authorName) {
    	$this->authorName = $authorName;
    }
    
    public function getAuthorName()
    {
    	if (null === $this->getAuthor()) {
    		return 'Anonymous';
    	}
    
    	return $this->getAuthor()->getUsername();
    }
    
    public function __toString() {
    	return $this->getBody();
    }
}
