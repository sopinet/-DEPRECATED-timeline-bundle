<?php

namespace Trazeo\BaseBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandler;
use FOS\RestBundle\View\RouteRedirectView;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Route;
use FOS\RestBundle\Controller\Annotations\RouteResource;
use Symfony\Component\HttpFoundation\Response;
use Trazeo\BaseBundle\Entity\ERide;
use Trazeo\BaseBundle\Entity\EEvent;
use Trazeo\BaseBundle\Entity\EReport;
use Sopinet\Bundle\SimplePointBundle\ORM\Type\SimplePoint;
use Sopinet\TimelineBundle\Entity\Comment;

class ApiController extends Controller {
	
	/**
	 * Funcion para representar un acceso denegado a la API
	 */
	private function msgDenied() {
		$array['state'] = -1;
		$array['msg'] = "Access Denied";
		return $array;
	}
	
	private function msgOk() {
		$view = view::create()
		->setStatusCode(200)
		->setData($this->doOk(null));
	
		return $this->handleView($view);
	}
	
	/**
	 * Funcion para representar un acceso valido a la API
	 * @param array $data Serie de datos
	 * @return array Serie de datos
	 */
	private function doOK($data) {
		$ret['state'] = 1;
		$ret['msg'] = "Ok";
		if($data == null) {
			$arr[] = null;
			$ret['data'] = $arr;
		}
		else
			$ret['data'] = $data;
		return $ret;
	}
	
	/**
	 * Funcion que controla el usuario que envia datos a la API, sin estar logueado, con parámetros email y pass
	 */
	private function checkUser($email, $password){
	
		$user = $this->getDoctrine()->getRepository('\Application\Sonata\UserBundle\Entity\User')->findOneBy(array ("email"=>$email, "password"=>$password));
				
		if ($user == null){
			$user = $this->getDoctrine()->getRepository('\Application\Sonata\UserBundle\Entity\User')->findOneBy(array ("username"=>$email, "password"=>$password));
			if ($user == null){
				return false;
			}
		}
		if ($password == $user->getPassword()){
			return $user;
		}
		else
			return false;
	}
	
	/**
	 * Funcion que controla si el usuario está logueado o se comprueba con su email y pass
	 */
	private function checkPrivateAccess(Request $request) {
		//$user = $this->get('security.context')->getToken()->getUser();
	
		/*if ($user != null && $user != "anon.") {
			return $user;
		}*/
	
		// TODO: ACTIVAR, QUITAR FALSE / NO HACE FALTA ESTA COMPROBACION
// 		if ('POST' != $request->getMethod() && false) {
// 			return false;
// 		}
	
		$user = $this->checkUser($request->get('email'), $request->get('pass'));
	
		//No es necesario
		if($user == false) {
			return false;
		}
	
		return $user;
	}
	
	/**
	 * @POST("/api/login", name="api_login")
	 */
	public function postLoginAction(Request $request){
		//Se usan anotaciones para comprobar si el método es post
		//if ('POST' == $request->getMethod() || true) {
			$user = $this->checkPrivateAccess($request);
	
			if( $user == false || $user == null ){
				$view = View::create()
				->setStatusCode(200)
				->setData($this->msgDenied());
					
				return $this->get('fos_rest.view_handler')->handle($view);
			}
				
			$array['id'] = $user->getId();
		
			$view = View::create()
			->setStatusCode(200)
			->setData($this->doOK($array));
				
			return $this->get('fos_rest.view_handler')->handle($view);
		//}else
			//return $this->msgDenied();
	}
	
	/**
	 * @POST("/api/groups")
	 */
	public function getGroupsAction(Request $request) {
		
		$user = $this->checkPrivateAccess($request);
		if( $user == false || $user == null ){
			$view = View::create()
			->setStatusCode(200)
			->setData($this->msgDenied());
		
			return $this->get('fos_rest.view_handler')->handle($view);
		}
		
		$em = $this->get('doctrine.orm.entity_manager');
		$userextend = $em->getRepository('TrazeoBaseBundle:UserExtend')->findOneByUser($user);
		$groups = $userextend->getGroups();
		$array = array();
		foreach($groups as $group){
			$arrayGroups = array();
			$arrayGroups['id'] = $group->getId();
			$arrayGroups['name'] = $group->getName();
			$arrayGroups['visibility'] = $group->getVisibility();
			$arrayGroups['hasride'] = $group->getHasRide();
			
			$array[] = $arrayGroups;
		}
		$view = View::create()
		->setStatusCode(200)
		->setData($this->doOK($array));
		
		return $this->get('fos_rest.view_handler')->handle($view);
	}
	
	/**
	 * @POST("/api/ride/createNew")
	 */
	public function getCreateNewRideAction(Request $request) {
	
		//Comprobar si el ride asociado al grupo está creado(hasRide=1)
		$id_group = $request->get('id_group');
		$latitude = $request->get('latitude');
		$longitude = $request->get('longitude');
		
		$user = $this->checkPrivateAccess($request);
		if( $user == false || $user == null ){
			$view = View::create()
			->setStatusCode(200)
			->setData($this->msgDenied());
	
			return $this->get('fos_rest.view_handler')->handle($view);
		}
		
		$em = $this->get('doctrine.orm.entity_manager');
		
		$userextend = $em->getRepository('TrazeoBaseBundle:UserExtend')->findOneByUser($user);
		
		$group = $em->getRepository('TrazeoBaseBundle:EGroup')->findOneBy(array("id" => $id_group));
		$members = $group->getUserextendgroups()->toArray();
		
		if(in_array($userextend, $members)){

			// Si el grupo tiene Paseo asociado(está en marcha), devuelve el paseo
			if($group->getHasRide() == 1 && $group->getRide() != null){
				$array['id_ride'] = $group->getRide()->getId();
				
				$view = View::create()
				->setStatusCode(200)
				->setData($this->doOK($array));
				
				return $this->get('fos_rest.view_handler')->handle($view);
			}
			// Sino, se crea un paseo y se asocia al grupo
			else{ 				
				//Cerrar paseo asociado a este grupo, si los hubiera
				if($group->getRide() != null) {
					//Sacamos el paseo asociado
					$ride = $group->getRide();
					
					$group->setHasRide(0);
						
					$em->persist($group);
					$em->flush();
					
					//Cálculo del tiempo transcurrido en el paseo
					$inicio = $ride->getCreatedAt();
					$fin = new \DateTime();
						
					$diff = $inicio->diff($fin);
					$duration = $diff->h." horas, ".$diff->i." minutos y ".$diff->s." segundos";
						
					$ride->setDuration($duration);
					$ride->setGroupid($group->getId());
					$ride->setGroup(null);
					$em->persist($ride);
					$em->flush();
						
					$event = new EEvent();
					$event->setRide($ride);
					$event->setAction("finish");
					$event->setData("");
					
					$em->persist($event);
					
					$em->flush();
					
					
					$group->setRide(null);
				}
				
				$ride = new ERide();
				//TODO: En la relación Group-Ride, evitar los dos set
				$ride->setGroup($group);				
				$em->persist($ride);
				$group->setHasRide(1);
				$group->setRide($ride);
				$em->persist($group);
				$em->flush();
				
				$userextends = $group->getUserextendgroups()->toArray();
				
				$not = $this->container->get('sopinet_user_notification');
				foreach($userextends as $userextend)
				{
					$not->addNotification(
							"ride.start",
							"TrazeoBaseBundle:EGroup",
							$group->getId(),
							$this->generateUrl('panel_ride_current', array('id' => $ride->getId())),
							$userextend->getUser()
					);
				}
				
				$event = new EEvent();
				$event->setRide($ride);
				$event->setAction("start");
				$event->setData("");
				$event->setLocation(new SimplePoint($latitude, $longitude));
				$em->persist($event);
				
				$array['id_ride'] = $group->getRide()->getId();
				
				$view = View::create()
				->setStatusCode(200)
				->setData($this->doOK($array));
				
				return $this->get('fos_rest.view_handler')->handle($view);
			}
			
		}
		
	}
	
	/**
	 * @POST("/api/ride/data")
	 */
	public function getDataRideAction(Request $request) {
	
		$id_ride = $request->get('id_ride');
	
		$user = $this->checkPrivateAccess($request);
		if( $user == false || $user == null ){
			$view = View::create()
			->setStatusCode(200)
			->setData($this->msgDenied());
	
			return $this->get('fos_rest.view_handler')->handle($view);
		}
	
		$em = $this->get('doctrine.orm.entity_manager');
	
		$userextend = $em->getRepository('TrazeoBaseBundle:UserExtend')->findOneByUser($user);
		
		$ride = $em->getRepository('TrazeoBaseBundle:ERide')->findOneById($id_ride);
		
		$view = View::create()
		->setStatusCode(200)
		->setData($this->doOK($ride));
			
		return $this->get('fos_rest.view_handler')->handle($view);
	
	}
	
	/**
	 * Guarda en el servidor la nueva posición del Paseo
	 * @POST("/api/ride/sendPosition")
	 */
	public function getSendPositionRideAction(Request $request) {
	
		$id_ride = $request->get('id_ride');
		$latitude = $request->get('latitude');
		$longitude = $request->get('longitude');
	
		$user = $this->checkPrivateAccess($request);
		if( $user == false || $user == null ){
			$view = View::create()
			->setStatusCode(200)
			->setData($this->msgDenied());
	
			return $this->get('fos_rest.view_handler')->handle($view);
		}
	
		$em = $this->get('doctrine.orm.entity_manager');
		
		//$userextend = $em->getRepository('TrazeoBaseBundle:UserExtend')->findOneByUser($user);
	
		$ride = $em->getRepository('TrazeoBaseBundle:ERide')->findOneById($id_ride);
		
		$event = new EEvent();
		$event->setRide($ride);
		$event->setAction("point");
		$event->setLocation(new SimplePoint($latitude, $longitude));
		$event->setData("");
		
		$em->persist($event);
		$em->flush();
		
		$view = View::create()
		->setStatusCode(200)
		->setData($this->doOK($event));
			
		return $this->get('fos_rest.view_handler')->handle($view);
	
	}
	
	/**
	 * Guarda en el servidor la nueva posición del Grupo
	 * @POST("/api/ride/sendChildInRide")
	 */
	public function getSendChildInRideAction(Request $request) {
	
		$id_ride = $request->get('id_ride');
		$id_child = $request->get('id_child');
		$latitude = $request->get('latitude');
		$longitude = $request->get('longitude');
	
		$user = $this->checkPrivateAccess($request);
		if( $user == false || $user == null ){
			$view = View::create()
			->setStatusCode(200)
			->setData($this->msgDenied());
	
			return $this->get('fos_rest.view_handler')->handle($view);
		}
	
		$em = $this->get('doctrine.orm.entity_manager');
	
		//$userextend = $em->getRepository('TrazeoBaseBundle:UserExtend')->findOneByUser($user);
	
		$ride = $em->getRepository('TrazeoBaseBundle:ERide')->findOneById($id_ride);
		$child = $em->getRepository('TrazeoBaseBundle:EChild')->findOneById($id_child);
		$userextends = $child->getUserextendchilds()->toArray();
	
		//Creamos evento de entrada de un niño
		$event = new EEvent();
		$event->setRide($ride);
		$event->setAction("in");
		$event->setData($id_child."/".$child->getNick());
		$event->setLocation(new SimplePoint($latitude, $longitude));
		$em->persist($event);
		$em->flush();
		
		//Registramos al niño dentro del paseo
		$child->setRide($ride);
		$child->setSelected(1);
		$em->persist($child);
		$em->flush();
		
		//Notificamos a sus tutores
		foreach($userextends as $userextend){
			$not = $this->container->get('sopinet_user_notification');
			$not->addNotification(
					"child.in",
					"TrazeoBaseBundle:EChild,TrazeoBaseBundle:EGroup",
					$child->getId() . "," . $ride->getGroup()->getId(),
					$this->generateUrl('panel_ride_current', array('id' => $ride->getId())),
					$userextend->getUser()
			);
		}
	
		$array['selected'] = $child->getSelected();
	
		$view = View::create()
		->setStatusCode(200)
		->setData($this->doOK($array));
			
		return $this->get('fos_rest.view_handler')->handle($view);
	
	}
	
	/**
	 * Guarda en el servidor la nueva posición del Grupo
	 * @POST("/api/ride/sendChildOutRide")
	 */
	public function getSendChildOutRideAction(Request $request) {
	
		$id_ride = $request->get('id_ride');
		$id_child = $request->get('id_child');
		$latitude = $request->get('latitude');
		$longitude = $request->get('longitude');
	
		$user = $this->checkPrivateAccess($request);
		if( $user == false || $user == null ){
			$view = View::create()
			->setStatusCode(200)
			->setData($this->msgDenied());
	
			return $this->get('fos_rest.view_handler')->handle($view);
		}
	
		$em = $this->get('doctrine.orm.entity_manager');
	
		//$userextend = $em->getRepository('TrazeoBaseBundle:UserExtend')->findOneByUser($user);
	
		$ride = $em->getRepository('TrazeoBaseBundle:ERide')->findOneById($id_ride);
		$child = $em->getRepository('TrazeoBaseBundle:EChild')->findOneById($id_child);
		$userextends = $child->getUserextendchilds()->toArray();
	
		$event = new EEvent();
		$event->setRide($ride);
		$event->setAction("out");
		$event->setData($id_child."/".$child->getNick());
		$event->setLocation(new SimplePoint($latitude, $longitude));
		$em->persist($event);
		$em->flush();
		
		//Eliminamos el niño del paseo
		$child->setRide(null);
		$child->setSelected(0);
		$em->persist($child);
		$em->flush();
		
		$not = $this->container->get('sopinet_user_notification');
		//Notificamos a sus tutores
		foreach($userextends as $userextend){
			$not->addNotification(
					"child.out",
					"TrazeoBaseBundle:EChild,TrazeoBaseBundle:EGroup",
					$child->getId() . "," . $ride->getGroup()->getId(),
					$this->generateUrl('panel_ride_current', array('id' => $ride->getId())),
					$userextend->getUser()
			);
		}
		
		$array['selected'] = $child->getSelected();
	
		$view = View::create()
		->setStatusCode(200)
		->setData($this->doOK($array));
			
		return $this->get('fos_rest.view_handler')->handle($view);
	
	}
	
	/**
	 * Mandar último punto del paseo
	 * @POST("/api/ride/lastPoint")
	 */
	public function getlastPointRideAction(Request $request) {
	
		$id_ride = $request->get('id_ride');
		$latitude = $request->get('latitude');
		$longitude = $request->get('longitude');
	
		$user = $this->checkPrivateAccess($request);
		if( $user == false || $user == null ){
			$view = View::create()
			->setStatusCode(200)
			->setData($this->msgDenied());
	
			return $this->get('fos_rest.view_handler')->handle($view);
		}
	
		$em = $this->get('doctrine.orm.entity_manager');
	
		//$userextend = $em->getRepository('TrazeoBaseBundle:UserExtend')->findOneByUser($user);
		$reEvent = $em->getRepository('TrazeoBaseBundle:EEvent');
		
		$ride = $em->getRepository('TrazeoBaseBundle:ERide')->findOneById($id_ride);
		// TODO: Lo ideal sería coger el último PUNTO con un REPOSITORY
		$events = $reEvent->findBy(array('action' => "point", 'ride' => $ride->getId()), array('createdAt' => 'DESC'));
	
		if (count($events) > 0) {
			$data = $events[0];
		} else {
			$data = null;
		}
		
		$view = View::create()
		->setStatusCode(200)
		->setData($this->doOK($data));
			
		return $this->get('fos_rest.view_handler')->handle($view);
	
	}
	
	/**
	 * @POST("/api/ride/finish")
	 */
	public function getFinishRideAction(Request $request) {
	
		//Comprobar si el ride asociado al grupo está creado(hasRide=1)
		$id_ride = $request->get('id_ride');
		$latitude = $request->get('latitude');
		$longitude = $request->get('longitude');
	
		$user = $this->checkPrivateAccess($request);
		if( $user == false || $user == null ){
			$view = View::create()
			->setStatusCode(200)
			->setData($this->msgDenied());
	
			return $this->get('fos_rest.view_handler')->handle($view);
		}
	
		$em = $this->get('doctrine.orm.entity_manager');
	
		$userextend = $em->getRepository('TrazeoBaseBundle:UserExtend')->findOneByUser($user);
		
		$ride = $em->getRepository('TrazeoBaseBundle:ERide')->find($id_ride);
		$group = $ride->getGroup();
		
		//Cálculo del tiempo transcurrido en el paseo
		$inicio = $ride->getCreatedAt();
		$fin = new \DateTime();
		
		$diff = $inicio->diff($fin);
		$duration = $diff->h." horas, ".$diff->i." minutos y ".$diff->s." segundos";
		
		$group->setHasRide(0);
		$em->persist($group);
		
		$ride->setDuration($duration);
		$ride->setGroupid($group->getId());
		$ride->setGroup(null);
		$em->persist($ride);
		
		$event = new EEvent();
		$event->setRide($ride);
		$event->setAction("finish");
		$event->setData("");
		$event->setLocation(new SimplePoint($latitude, $longitude));
		$em->persist($event);

		$em->flush();
		
		$userextends = $group->getUserextendgroups();
		
		$not = $this->container->get('sopinet_user_notification');
		foreach($userextends as $userextend)
		{
			$not->addNotification(
					"ride.finish",
					"TrazeoBaseBundle:EGroup",
					$group->getId(),
					$this->generateUrl('panel_dashboard'),
					$userextend->getUser()
			);
		}
			
		$view = View::create()
		->setStatusCode(200)
		->setData($this->doOK("ok"));
			
		return $this->get('fos_rest.view_handler')->handle($view);
		
	
	}
	
	/**
	 * Guarda en el servidor la nueva posición del Grupo
	 * @POST("/api/ride/report")
	 */
	public function getReportAction(Request $request) {
	
		$id_ride = $request->get('id_ride');
		$texto = $request->get('text');
		$latitude = $request->get('latitude');
		$longitude = $request->get('longitude');
		//$tipo_de_incidencia = $request->get('tipo_de_incidencia');
	
		$user = $this->checkPrivateAccess($request);
		if( $user == false || $user == null ){
			$view = View::create()
			->setStatusCode(200)
			->setData($this->msgDenied());
	
			return $this->get('fos_rest.view_handler')->handle($view);
		}
	
		$em = $this->get('doctrine.orm.entity_manager');
	
		$userextend = $em->getRepository('TrazeoBaseBundle:UserExtend')->findOneByUser($user);
	
		$ride = $em->getRepository('TrazeoBaseBundle:ERide')->findOneById($id_ride);
	
		$report = new EReport();
		$report->setText($texto);
		$report->setUserextend($userextend);
		$report->setRide($ride);
		//$report->setType($tipo_de_incidencia);

		$em->persist($report);
		$em->flush();
		$event = new EEvent();
		$event->setRide($ride);
		$event->setAction("report");
		$event->setData($report->getId()."/".$texto);
		$event->setLocation(new SimplePoint($latitude, $longitude));
		
		$em->persist($event);
		$em->flush();
		
		$array['id'] = $report->getId();
		
		$view = View::create()
		->setStatusCode(200)
		->setData($this->doOK($array));
			
		return $this->get('fos_rest.view_handler')->handle($view);
	}
	
	/**
	 * Lista los mensajes del TimeLine (Muro) del Grupo
	 * 
	 * @POST("/api/group/timeline/list")
	 * @param Request $request
	 */	
	public function getTimeLineAction(Request $request) {
		$user = $this->checkPrivateAccess($request);
		if( $user == false || $user == null ){
			$view = View::create()
			->setStatusCode(200)
			->setData($this->msgDenied());
		
			return $this->get('fos_rest.view_handler')->handle($view);
		}
		
		$em = $this->get('doctrine.orm.entity_manager');
		
		$id_group = $request->get('id_group');
		
		$thread = $em->getRepository('SopinetTimelineBundle:Thread')->findOneById($id_group);
		$comments = $em->getRepository('SopinetTimelineBundle:Comment')->findByThread($thread);
		$data = array();
		foreach($comments as $comment) {
			$comment->setAuthorName($comment->getAuthorName());
			$data[] = $comment;
		}
		
		$view = View::create()
		->setStatusCode(200)
		->setData($this->doOK($data));
			
		return $this->get('fos_rest.view_handler')->handle($view);
	}
	
	/**
	 * Crea un nuevo mensaje en el TimeLine (Muro) del Grupo
	 * 
	 * @POST("/api/group/timeline/new")
	 * @param Request $request
	 */
	public function newTimeLineAction(Request $request) {
		$user = $this->checkPrivateAccess($request);
		if( $user == false || $user == null ){
			$view = View::create()
			->setStatusCode(200)
			->setData($this->msgDenied());
		
			return $this->get('fos_rest.view_handler')->handle($view);
		}
		$em = $this->get('doctrine.orm.entity_manager');
		$id_group = $request->get('id_group');
		$text = $request->get('text');
		
		$group = $em->getRepository('TrazeoBaseBundle:EGroup')->findOneById($id_group);
		$thread = $em->getRepository('SopinetTimelineBundle:Thread')->findOneById($id_group);
		
		// Save comment
		$comment = new Comment();
		$comment->setThread($thread);
		$comment->setAuthor($user);
		$comment->setBody($text);
		
		$em->persist($comment);
		$em->flush();
		
		// Send notifications to Users
		$userextends = $group->getUserextendgroups()->toArray();
		$not = $this->container->get('sopinet_user_notification');
		foreach($userextends as $userextend)
		{
			$not->addNotification(
					"timeline.newFromMonitor",
					"TrazeoBaseBundle:EGroup,SopinetTimelineBundle:Comment",
					$group->getId().",".$comment->getId(),
					$this->generateUrl('panel_group_timeline', array('id' => $group->getId())),
					$userextend->getUser()
			);
		}	
		
		$view = View::create()
		->setStatusCode(200)
		->setData($this->doOK($comment));
			
		return $this->get('fos_rest.view_handler')->handle($view);		
	}
}
