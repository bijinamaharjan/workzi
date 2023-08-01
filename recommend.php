<style>

.recommendations {
  width: 600px;
  margin: 0 auto; 
  text-align: center;
  padding: 5em;
}

.recommendation {
  text-align: left; 
}

.btn {
  display: inline-block;
  padding: 10px 20px;
  background: #007bff;
  color: #fff;
  text-decoration: none;
}
</style>
<?php

// Helper functions
function matrixmult($matrix_a, $matrix_b) {
    $matrix_a_count=count($matrix_a);
    $c=count($matrix_b[0]);
    $matrix_b_count=count($matrix_b);
    if(count($matrix_a[0])!=$matrix_b_count){throw new Exception('Incompatible matrices');}
    $matrix_return=array();
    for ($i=0;$i< $matrix_a_count;$i++){
        for($j=0;$j<$c;$j++){
            $matrix_return[$i][$j]=0;
            for($k=0;$k<$matrix_b_count;$k++){
                $matrix_return[$i][$j]+=$matrix_a[$i][$k]*$matrix_b[$k][$j];
            }
        }
    }
    return($matrix_return);
}

function generateRandomArray($dim, $num) {
    $newArray = array();
    for($i = 0; $i < $dim; $i++){
        for($j = 0; $j < $num; $j++){
            $newArray[$i][$j] = mt_rand() / mt_getrandmax();
        }
    }
    return $newArray;
}

function transpose($array) {
    array_unshift($array, null);
    return call_user_func_array('array_map', $array);
}

// Matrix Factorization Function
function matrix_factorization($R, $P, $Q, $K, $steps = 5000, $alpha = 0.0002, $beta = 0.02) {
    $Q = transpose($Q);
	for($step = 0; $step<$steps; $step++){
		for($i = 0; $i<count($R); $i++){
			for($j = 0; $j<count($R[$i]); $j++){
				if ($R[$i][$j] > 0){
					$sigmaPQ = 0;
					for($z = 0; $z < $K; $z++){
						$sigmaPQ += $P[$i][$z] * $Q[$z][$j];
					}
                    $eij = $R[$i][$j] - $sigmaPQ;
                    for ($k = 0; $k < $K; $k++){
                        $P[$i][$k] = $P[$i][$k] + $alpha * (2 * $eij * $Q[$k][$j] - $beta * $P[$i][$k]);
						$Q[$k][$j] = $Q[$k][$j] + $alpha * (2 * $eij * $P[$i][$k] - $beta * $Q[$k][$j]);
					}
				}
			}
		}
        $e = 0;
		for ($i = 0; $i < count($R); $i++){
            for ($j = 0; $j < count($R[$i]); $j++){
                if ($R[$i][$j] > 0){
					//pow(x, y, z) = x to the power of y modulo z.

					$sigmaPQ = 0;
					for($z = 0; $z < $K; $z++){
						$sigmaPQ += $P[$i][$z] * $Q[$z][$j];
					}
                    $e = $e + pow($R[$i][$j] - $sigmaPQ, 2);
                    for ($k = 0; $k < $K; $k++){
						$e = $e + ($beta/2) * ( pow($P[$i][$k],2) + pow($Q[$k][$j],2) );
					}
				}
			}
		}
        if ($e < 0.001){
			break;
		}
	}
    return [$P, transpose($Q)];
}

// Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "erisdb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT * FROM Views";
$result = $conn->query($sql);

$R = array();
$jobIds = array();
$applicantIds = array();
$visitCounts = array();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $R[$row["applicant_id"]][$row["job_id"]] = $row["visit_count"];
        $jobIds[$row["job_id"]] = true;
        $applicantIds[$row["applicant_id"]] = true;
        $visitCounts[$row["applicant_id"]][$row["job_id"]] = $row["visit_count"];
    }
}

$jobIds = array_keys($jobIds);
$applicantIds = array_keys($applicantIds);
$N = count($applicantIds);
$M = count($jobIds);
$K = 2; // Number of latent factors

// Prepare the "visit_count" matrix for matrix factorization
$R_visit_count = array();
for ($i = 0; $i < $N; $i++) {
    for ($j = 0; $j < $M; $j++) {
        $R_visit_count[$i][$j] = isset($visitCounts[$applicantIds[$i]][$jobIds[$j]]) ? $visitCounts[$applicantIds[$i]][$jobIds[$j]] : 0;
    }
}

$P = generateRandomArray($N, $K);
$Q = generateRandomArray($M, $K);

// Perform Matrix Factorization on the "visit_count" matrix
$calculatedRatingsMatrix = matrix_factorization($R_visit_count, $P, $Q, $K);

// Update Recommendations Table
$predictedRatings = matrixmult($calculatedRatingsMatrix[0], transpose($calculatedRatingsMatrix[1]));

// Clear the existing recommendations for all applicants to regenerate them
$sql = "DELETE FROM Recommendations";
$conn->query($sql);

// Insert the newly predicted ratings into the Recommendations table
for ($i = 0; $i < $N; $i++) {
    for ($j = 0; $j < $M; $j++) {
        $applicant_id = $applicantIds[$i];
        $job_id = $jobIds[$j];
        $predicted_rating = $predictedRatings[$i][$j];
        $sql = "INSERT INTO Recommendations (applicant_id, job_id, predicted_rating) VALUES ($applicant_id, $job_id, $predicted_rating)";
        $conn->query($sql);
    }
}

// Display Recommendations for Logged-In User
if (isset($_SESSION['APPLICANTID'])) {
    $applicant_id = $_SESSION['APPLICANTID'];
    $sql = "SELECT * FROM Recommendations WHERE applicant_id = $applicant_id ORDER BY predicted_rating DESC LIMIT 10";
    $result = $conn->query($sql);
?>
<div class="recommendations">
  
  <?php if ($result->num_rows > 0): ?>
  
    <?php while ($row = $result->fetch_assoc()): ?>
    
      <div class="recommendation">
      
        <h3>
          <?php 
            // Get job title
            $sql = "SELECT * FROM tbljob WHERE JOBID = " . $row["job_id"];
            $result2 = $conn->query($sql);
            if ($result2->num_rows > 0) {
              $row2 = $result2->fetch_assoc();
              echo $row2["OCCUPATIONTITLE"];
            }
          ?>
        </h3>
        
        <p>Predicted Rating: <?php echo $row["predicted_rating"]; ?></p>
        
        <a class="btn" href="index.php?q=viewjob&search=<?php echo $row["job_id"]; ?>">
          View Job
        </a>
        
      </div>
      
    <?php endwhile; ?>
    
  <?php else: ?>
  
    <p>No recommendations available for this user.</p>
    
  <?php endif; ?>
  
</div>
<?php
}

// Close the database connection
$conn->close();

?>
