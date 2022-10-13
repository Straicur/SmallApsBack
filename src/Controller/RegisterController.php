<?php

namespace App\Controller;

use App\Entity\AuthenticationToken;
use App\Entity\RegisterCode;
use App\Entity\User;
use App\Entity\UserInformation;
use App\Exception\DataNotFoundException;
use App\Exception\InvalidJsonDataException;
use App\Model\AuthorizationSuccessModel;
use App\Model\DataNotFoundModel;
use App\Model\JsonDataInvalidModel;
use App\Query\RegisterConfirmQuery;
use App\Query\RegisterConfirmSendQuery;
use App\Query\RegisterQuery;
use App\Repository\AuthenticationTokenRepository;
use App\Repository\RegisterCodeRepository;
use App\Repository\UserInformationRepository;
use App\Repository\UserPasswordRepository;
use App\Repository\UserRepository;
use App\Service\RequestServiceInterface;
use App\Tool\ResponseTool;
use App\ValueGenerator\AuthTokenGenerator;
use App\ValueGenerator\RegisterCodeGenerator;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * RegisterController
 */
#[OA\Response(
    response: 400,
    description: "JSON Data Invalid",
    content: new Model(type: JsonDataInvalidModel::class)
)]
#[OA\Response(
    response: 404,
    description: "Data not found",
    content: new Model(type: DataNotFoundModel::class)
)]
#[OA\Tag(name: "Register")]
class RegisterController extends AbstractController
{
    /**
     * @param Request $request
     * @param RequestServiceInterface $requestServiceInterface
     * @param UserInformationRepository $userInformationRepository
     * @param UserRepository $userRepository
     * @param LoggerInterface $endpointLogger
     * @param RegisterCodeRepository $registerCodeRepository
     * @param MailerInterface $mailer
     * @return Response
     * @throws DataNotFoundException
     * @throws InvalidJsonDataException
     * @throws TransportExceptionInterface
     */
    #[Route("/api/register", name: "apiRegister", methods: ["PUT"])]
    #[OA\Put(
        description: "Method used to register user",
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: new Model(type: RegisterQuery::class),
                type: "object"
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
            ),
        ]
    )]
    public function register(
        Request                   $request,
        RequestServiceInterface   $requestServiceInterface,
        UserInformationRepository $userInformationRepository,
        UserRepository            $userRepository,
        LoggerInterface           $endpointLogger,
        RegisterCodeRepository    $registerCodeRepository,
        MailerInterface           $mailer,
    ): Response
    {
        $registerQuery = $requestServiceInterface->getRequestBodyContent($request, RegisterQuery::class);

        if ($registerQuery instanceof RegisterQuery) {

            $duplicateUser = $userInformationRepository->findOneBy([
                "email" => $registerQuery->getEmail()
            ]);

            if ($duplicateUser != null) {
                $endpointLogger->error("Email already exists");
                throw new DataNotFoundException(["register.put.invalid.email"]);
            }

            $newUser = new User();

            $newUser->setUserInformation(new UserInformation(
                $newUser,
                $registerQuery->getEmail(),
                $registerQuery->getPhoneNumber(),
                $registerQuery->getFirstname(),
                $registerQuery->getLastname()
            ));

            $userRepository->add($newUser);

            $registerCodeGenerator = new RegisterCodeGenerator();

            $registerCode = new RegisterCode($newUser, $registerCodeGenerator);

            $registerCodeRepository->add($registerCode);

            $email = (new TemplatedEmail())
                ->from('mosinskidamian12@gmail.com')
                ->to($newUser->getUserInformation()->getEmail())
                ->subject('Kod aktywacji konta')
                ->htmlTemplate('emails/register.html.twig')
                ->context([
                    "userName" => $newUser->getUserInformation()->getFirstname() . ' ' . $newUser->getUserInformation()->getLastname(),
                    "code" => $registerCode->getCode()
                ]);

            $mailer->send($email);

            return ResponseTool::getResponse();
        } else {
            $endpointLogger->error("Invalid given Query");
            throw new InvalidJsonDataException("register.put.invalid.query");
        }
    }

    /**
     * @param Request $request
     * @param RequestServiceInterface $requestServiceInterface
     * @param LoggerInterface $usersLogger
     * @param UserRepository $userRepository
     * @param UserPasswordRepository $userPasswordRepository
     * @param AuthenticationTokenRepository $authenticationTokenRepository
     * @param LoggerInterface $endpointLogger
     * @param RegisterCodeRepository $registerCodeRepository
     * @return Response
     * @throws DataNotFoundException
     * @throws InvalidJsonDataException
     */
    #[Route("/api/register/code", name: "apiRegisterConfirm", methods: ["PATCH"])]
    #[OA\Patch(
        description: "Method used to confirm user registration",
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: new Model(type: RegisterConfirmQuery::class),
                type: "object"
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new Model(type: AuthorizationSuccessModel::class)
            ),
        ]
    )]
    public function registerConfirm(
        Request                       $request,
        RequestServiceInterface       $requestServiceInterface,
        LoggerInterface               $usersLogger,
        UserRepository                $userRepository,
        UserPasswordRepository        $userPasswordRepository,
        AuthenticationTokenRepository $authenticationTokenRepository,
        LoggerInterface               $endpointLogger,
        RegisterCodeRepository $registerCodeRepository
    ): Response
    {
        $registerConfirmQuery = $requestServiceInterface->getRequestBodyContent($request, RegisterConfirmQuery::class);

        if ($registerConfirmQuery instanceof RegisterConfirmQuery) {

            $user = $userRepository->findOneBy([
                "id" => $registerConfirmQuery->getUserId()
            ]);

            if ($user == null) {
                $endpointLogger->error("Invalid Credentials");
                throw new DataNotFoundException(["user.credentials"]);
            }

            $registerCode = $registerCodeRepository->findOneBy([
                "code"=>$registerConfirmQuery->getRegisterCode(),
                "user"=>$user->getId()
            ]);

            if ($registerCode == null || $registerCode->getDateAccept() != null) {
                $endpointLogger->error("Invalid Credentials");
                throw new DataNotFoundException(["code.credentials"]);
            }

            $registerCodeRepository->add($registerCode->setDateAdd(new \DateTime('Now')));

            $passwordEntity = $userPasswordRepository->findOneBy([
                "user" => $user,
            ]);

            if ($passwordEntity == null) {
                $endpointLogger->error("Invalid Credentials");
                throw new DataNotFoundException(["password.credentials"]);
            }

            $authTokenGenerator = new AuthTokenGenerator($user);

            $authenticationToken = new AuthenticationToken($user, $authTokenGenerator);
            $authenticationTokenRepository->add($authenticationToken);

            $usersLogger->info("LOGIN", [$user->getId()->__toString()]);

            $responseModel = new AuthorizationSuccessModel($authenticationToken->getToken());

            return ResponseTool::getResponse($responseModel);

        } else {
            $endpointLogger->error("Invalid given Query");
            throw new InvalidJsonDataException("register.confirm.patch.invalid.query");
        }
    }

    /**
     * @param Request $request
     * @param RequestServiceInterface $requestServiceInterface
     * @param UserInformationRepository $userInformationRepository
     * @param LoggerInterface $endpointLogger
     * @param MailerInterface $mailer
     * @param RegisterCodeRepository $registerCodeRepository
     * @return Response
     * @throws DataNotFoundException
     * @throws InvalidJsonDataException
     * @throws TransportExceptionInterface
     */
    #[Route("/api/register/code/send", name: "apiRegisterCodeSend", methods: ["POST"])]
    #[OA\Post(
        description: "Method used to send registration code again",
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: new Model(type: RegisterConfirmSendQuery::class),
                type: "object"
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
            ),
        ]
    )]
    public function registerCodeSend(
        Request                   $request,
        RequestServiceInterface   $requestServiceInterface,
        UserInformationRepository $userInformationRepository,
        LoggerInterface           $endpointLogger,
        MailerInterface           $mailer,
        RegisterCodeRepository    $registerCodeRepository
    ): Response
    {
        $registerConfirmSendQuery = $requestServiceInterface->getRequestBodyContent($request, RegisterConfirmSendQuery::class);

        if ($registerConfirmSendQuery instanceof RegisterConfirmSendQuery) {

            $user = $userInformationRepository->findOneBy([
                "email" => $registerConfirmSendQuery->getEmail()
            ])->getUser();

            if ($user == null) {
                $endpointLogger->error("Invalid Credentials");
                throw new DataNotFoundException(["user.credentials"]);
            }

            $registerCode = $registerCodeRepository->getUsedCode($user);

            if ($registerCode != null) {
                $endpointLogger->error("Invalid Credentials");
                throw new DataNotFoundException(["code.credentials"]);
            }

            $registerCodeGenerator = new RegisterCodeGenerator();

            $registerCode = new RegisterCode($user, $registerCodeGenerator);

            $registerCodeRepository->add($registerCode);

            $email = (new TemplatedEmail())
                ->from('mosinskidamian12@gmail.com')
                ->to($user->getUserInformation()->getEmail())
                ->subject('Kod aktywacji konta')
                ->htmlTemplate('emails/register.html.twig')
                ->context([
                    "userName" => $user->getUserInformation()->getFirstname() . ' ' . $user->getUserInformation()->getLastname(),
                    "code" => $registerCode->getCode()
                ]);

            $mailer->send($email);

            return ResponseTool::getResponse();
        } else {
            $endpointLogger->error("Invalid given Query");
            throw new InvalidJsonDataException("register.code.send.invalid.query");
        }
    }
}