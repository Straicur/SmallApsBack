<?php

namespace App\Tests\Controller\NotebookController;

use App\Repository\NotebookNoteRepository;
use App\Tests\AbstractWebTest;

/**
 * NotebookNoteDeleteTest
 */
class NotebookNoteDeleteTest extends AbstractWebTest
{
    /**
     * step 1 - Preparing data
     * step 2 - Preparing JsonBodyContent
     * step 3 - Sending Request
     * step 4 - Checking response
     * step 5 - Checking response if note returned data is correct
     * @return void
     */
    public function test_notebookNoteDetailsCorrect(): void
    {
        $notebookNoteRepository = $this->getService(NotebookNoteRepository::class);

        $this->assertInstanceOf(NotebookNoteRepository::class, $notebookNoteRepository);
        /// step 1
        $user = $this->databaseMockManager->testFunc_addUser("User", "Test", "test@cos.pl", "+48123123123", ["Guest", "User"], true, "zaq12wsx");
        $notebookCategory = $this->databaseMockManager->testFunc_addNotebookCategory("test",$user);
        $note = $this->databaseMockManager->testFunc_addNotebookNote($notebookCategory,"test","text");

        $token = $this->databaseMockManager->testFunc_loginUser($user);

        /// step 3
        $crawler = self::$webClient->request("DELETE", "/api/notebook/note/".$note->getId()->__toString(), server: [
        "HTTP_authorization" => $token->getToken()
        ]);
        /// step 4
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $this->assertCount(0,$notebookNoteRepository->findAll());
    }
    /**
     * step 1 - Creating normal
     * step 2 - Preparing data
     * step 3 - Sending Request with bad permission
     * step 4 - Checking response
     *
     * @return void
     */
    public function test_notebookNoteDetailsIncorrectPermission(): void
    {
        /// step 1
        $user = $this->databaseMockManager->testFunc_addUser("User", "Test", "test@cos.pl", "+48123123123", ["Guest"], true, "zaq12wsx");
        $notebookCategory = $this->databaseMockManager->testFunc_addNotebookCategory("test",$user);
        $note = $this->databaseMockManager->testFunc_addNotebookNote($notebookCategory,"test","text");

        $token = $this->databaseMockManager->testFunc_loginUser($user);

        /// step 3
        $crawler = self::$webClient->request("DELETE", "/api/notebook/note/".$note->getId()->__toString(), server: [
            "HTTP_authorization" => $token->getToken()
        ]);
        /// step 4
        $this->assertResponseStatusCodeSame(403);

        $responseContent = self::$webClient->getResponse()->getContent();

        $this->assertNotNull($responseContent);
        $this->assertNotEmpty($responseContent);
        $this->assertJson($responseContent);

        $responseContent = json_decode($responseContent, true);

        $this->assertIsArray($responseContent);
        $this->assertArrayHasKey("error", $responseContent);
    }

    /**
     * step 1 - Preparing JsonBodyContent
     * step 2 - Sending Request as a bad user
     * step 3 - Checking response
     * @return void
     */
    public function test_notebookNoteDetailsIncorrectCredentials(): void
    {
        /// step 1
        $user1 = $this->databaseMockManager->testFunc_addUser("User", "Test", "test1@cos.pl", "+48123123123", ["Guest", "User"], true, "zaq12wsx");
        /// step 1
        $user = $this->databaseMockManager->testFunc_addUser("User", "Test", "test2@cos.pl", "+48123123123", ["Guest", "User"], true, "zaq12wsx");
        $notebookCategory = $this->databaseMockManager->testFunc_addNotebookCategory("test",$user);
        $note = $this->databaseMockManager->testFunc_addNotebookNote($notebookCategory,"test","text");

        $token = $this->databaseMockManager->testFunc_loginUser($user1);

        /// step 3
        $crawler = self::$webClient->request("DELETE", "/api/notebook/note/".$note->getId()->__toString(), server: [
            "HTTP_authorization" => $token->getToken()
        ]);
        /// step 3
        $this->assertResponseStatusCodeSame(404);

        $response = self::$webClient->getResponse();

        $responseContent = json_decode($response->getContent(), true);

        $this->assertIsArray($responseContent);
        $this->assertArrayHasKey("error", $responseContent);
        $this->assertArrayHasKey("data", $responseContent);
    }
    /**
     * step 1 - Preparing JsonBodyContent
     * step 2 - Sending Request as a bad user
     * step 3 - Checking response
     * @return void
     */
    public function test_notebookNoteDetailsIncorrectNoteCredentials(): void
    {
        /// step 1
        $user = $this->databaseMockManager->testFunc_addUser("User", "Test", "test@cos.pl", "+48123123123", ["Guest", "User"], true, "zaq12wsx");
        $notebookCategory = $this->databaseMockManager->testFunc_addNotebookCategory("test",$user);
        $note = $this->databaseMockManager->testFunc_addNotebookNote($notebookCategory,"test","text");

        $token = $this->databaseMockManager->testFunc_loginUser($user);

        /// step 3
        $crawler = self::$webClient->request("DELETE", "/api/notebook/note/66666c4e-16e6-1ecc-9890-a7e8b0073d3b", server: [
            "HTTP_authorization" => $token->getToken()
        ]);
        /// step 3
        $this->assertResponseStatusCodeSame(404);

        $response = self::$webClient->getResponse();

        $responseContent = json_decode($response->getContent(), true);

        $this->assertIsArray($responseContent);
        $this->assertArrayHasKey("error", $responseContent);
        $this->assertArrayHasKey("data", $responseContent);
    }
    /**
     * step 1 - Sending Request without content
     * step 2 - Checking response
     * @return void
     */
    public function test_notebookNoteDetailsEmptyRequest()
    {
        /// step 1
        $user = $this->databaseMockManager->testFunc_addUser("User", "Test", "test@cos.pl", "+48123123123", ["Guest", "User"], true, "zaq12wsx");
        $notebookCategory = $this->databaseMockManager->testFunc_addNotebookCategory("test",$user);
        $note = $this->databaseMockManager->testFunc_addNotebookNote($notebookCategory,"test","text");

        $token = $this->databaseMockManager->testFunc_loginUser($user);

        /// step 3
        $crawler = self::$webClient->request("DELETE", "/api/notebook/note/", server: [
            "HTTP_authorization" => $token->getToken()
        ]);
        /// step 2
        $this->assertResponseStatusCodeSame(404);

        $response = self::$webClient->getResponse();

        $responseContent = json_decode($response->getContent(), true);

        $this->assertIsArray($responseContent);
        $this->assertArrayHasKey("error", $responseContent);
        $this->assertArrayHasKey("data", $responseContent);
    }

    /**
     * step 1 - Preparing data
     * step 2 - Sending Request without token
     * step 3 - Checking response
     *
     * @return void
     */
    public function test_notebookNoteDetailsLogOut(): void
    {
        /// step 1
        $user = $this->databaseMockManager->testFunc_addUser("User", "Test", "test@cos.pl", "+48123123123", ["Guest", "User"], true, "zaq12wsx");
        $notebookCategory = $this->databaseMockManager->testFunc_addNotebookCategory("test",$user);
        $note = $this->databaseMockManager->testFunc_addNotebookNote($notebookCategory,"test","text");

        /// step 3
        $crawler = self::$webClient->request("DELETE", "/api/notebook/note/".$note->getId()->__toString());
        /// step 3
        $this->assertResponseStatusCodeSame(401);

        $responseContent = self::$webClient->getResponse()->getContent();

        $this->assertNotNull($responseContent);
        $this->assertNotEmpty($responseContent);
        $this->assertJson($responseContent);

        $responseContent = json_decode($responseContent, true);

        $this->assertIsArray($responseContent);
        $this->assertArrayHasKey("error", $responseContent);
    }
}