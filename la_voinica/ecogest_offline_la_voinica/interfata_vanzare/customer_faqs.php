<?php
include "database_connection.php";
include "website_header.php";
?>
  
<section class="about-wthree" id="about">
    <h4 class="text-center">Frequently Asked Questions</h4>  
<style>.text-center{color:black;
font-size:2em;}</style><hr>
<div class="container">
    
    <script type="text/javascript" src="//code.jquery.com/jquery-1.11.0.min.js"></script>

    <script>
    $(document).ready(function() {
    
        $('.faq_question').click(function() {
    
            if ($(this).parent().is('.open')){
                $(this).closest('.faq').find('.faq_answer_container').animate({'height':'0'},500);
                $(this).closest('.faq').removeClass('open');
    
                }else{
                    var newHeight =$(this).closest('.faq').find('.faq_answer').height() +'px';
                    $(this).closest('.faq').find('.faq_answer_container').animate({'height':newHeight},500);
                    $(this).closest('.faq').addClass('open');
                }
    
        });
    
    });
    </script>
    <style>
    /*FAQS*/
    .faq_question {
        margin: 0px;
        padding: 0px 0px 5px 0px;
        display: inline-block;
        cursor: pointer;
        font-weight: bold;
        font-size:1.2em;
    }
    
    .faq_answer_container {
        height: 0px;
        overflow: hidden;
        padding: 0px;
        font-size:1.2em;
    }
    </style>


        <?php
$query = "SELECT * FROM $tabel_final_faqs ORDER BY faq_id ASC";  
                   $nrcrt=1;
                 $result = $pdo->query($query);
                 while($row = $result->fetch(PDO::FETCH_ASSOC)) 
                      {  
?>
            
            <div class="faq_container">
            <div class="faq">
                
                <div class="faq_question"><?php echo $nrcrt.". ".$row["question"]; ?></div>
                    <div class="faq_answer_container">
                        <div class="faq_answer"><?php echo $row["answer"]; ?></div>
                        
                    </div>        
                </div>
            </div>
                    
            <?php
            $nrcrt++;
            echo "<hr>";
            }

            ?>

        </div>
    </section>

<?php 
require "website_footer.php";
?>