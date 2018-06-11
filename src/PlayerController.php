<?php
namespace SpinTheWheel;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use mysqli;
use SpinTheWheel\PlayerNotFound;

class PlayerController
{
    public function __construct(mysqli $database) {
        $this->database = $database;
    }

    /**
    * Get player data from the players table based on player_id
    *
    * @param obj   $request   PSR-7 Request implmentation
    * @param obj   $response  PSR -7 Response implementation
    * @param array $args      Contents of the GET request
    */
    public function read(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $player =  $this->getPlayer($args["id"]);
        // Remove the salt_value from data returned by getPlayer.
        unset($player["salt_value"]);

        $response->getBody()->write(json_encode($player));
        return $response;
    }

    /**
    * Update player data in the players table with spin results.  Assumes one spin per request.
    *
    * @param obj   $request   PSR-7 Request implmentation
    * @param obj   $response  PSR -7 Response implementation
    * @param array $args      Contents of the PUT request.
    */
    public function spin(ServerRequestInterface $request, ResponseInterface $response, array $args){
        $body = $request->getParsedBody();

        $bet = $body["bet"];

        // Fail if $winnings is not an integer. Note negative numbers do not cause failure.
        if (($winnings = filter_var($body["winnings"], FILTER_VALIDATE_INT)) === false) {
            throw new \InvalidArgumentException(__CLASS__ . "::" . __FUNCTION__ . ": Invalid type given for winnings. Integer expected.");
        }

        // Fail if $bet is not an integer or is 0. Note negative numbers do not cause failure.
        if (!$bet = filter_var($body["bet"], FILTER_VALIDATE_INT)) {
            throw new \InvalidArgumentException(__CLASS__ . "::" . __FUNCTION__ . ": Invalid type given for bet. Integer expected.");
        }

        // Get Player details from database
        $player =  $this->getPlayer($args["id"]);

        // Confirm hash received in request matches database salt_value exactly.
        if($body["hash"] !== $player["salt_value"]) {
            throw new \SpinTheWheel\AuthenticationFail("Authentication failed for player_id " . $args["id"]);
        }

        //Apply coins bet and coins won to credits.  Increase lifetime spins by one.
        $credits = $player["credits"] + $winnings - $bet;
        $spins = $player["lifetime_spins"] + 1;

        // Update the database.
        $updateSuccess = $this->update($args["id"], $player["name"], $credits, $spins, $body["hash"]);

        // Get new player details.
        $updatedPlayer = $this->getPlayer($args["id"]);

        // Remove the salt_value from data returned by getPlayer
        unset($updatedPlayer["salt_value"]);
        $response->getBody()->write(json_encode($updatedPlayer));
        return $response;
    }

    /**
    * Extract data from players table based on player_id.
    *
    * @param integer $id player_id for player data is being extracted for
    */
    private function getPlayer(int $id)
    {
        $query = "SELECT player_id, name, credits, lifetime_spins, salt_value FROM players WHERE player_id = ?";

        if (!$stmt = $this->database->prepare($query)) {
            throw new \mysqli_sql_exception(
                __CLASS__ . '::' . __FUNCTION__ . "({$this->database->errno}): {$this->database->error}",
                $this->database->errno
            );
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $results = $stmt->get_result();

        //close prepared statement
        $stmt->close();

        if ($results->num_rows === 0) {
            throw new PlayerNotFound("Player not found with player_id " . $id);
        }

        $row = $results->fetch_assoc();

        //free up memory
        $results->free();

        // Get the Lifetime average return and place it in the player array
        $row["lifetime_average"] = $row["credits"] / $row["lifetime_spins"];

        return $row;
    }

    /**
    * Update players table.
    *
    * @param intger  $id        User's player_id
    * @param string  $name      User's name
    * @param integer $credits   The change being made to the User's total credits
    * @param integer $spins     The change being made to the User's lifetime_spins
    * @param string  $hash      User's salt value.
    */
    private function update(int $id, string $name, int $credits, int $spins, string $hash)
    {
        $query = "UPDATE players SET name = ?, credits = ?, lifetime_spins = ? WHERE player_id = ? AND salt_value = ?";

        // Create the statement.  Throw an error if it does not prepare appropriately.
        if (!$stmt = $this->database->prepare($query)) {
            throw new \mysqli_sql_exception(
                __CLASS__ . '::' . __FUNCTION__ . "({$this->database->errno}): {$this->database->error}",
                $this->database->errno
            );
        }

        $stmt->bind_param("siiis", $name, $credits, $spins, $id, $hash);
        $stmt->execute();

        // If the update is not performed (0 rows are affected in the table) throw a specifc error.
        if ($stmt->affected_rows == 0) {
            throw new \mysqli_sql_exception(
                __CLASS__ . '::' . __FUNCTION__ . "Update failed."
            );
        }

        //close prepared statement
        $stmt->close();

        return true;
    }
}
