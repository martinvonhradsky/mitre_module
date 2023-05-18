<!DOCTYPE html>
<html>
<head>
    <title>Hello, world!</title>
</head>
<body>
  <p><button onclick="getStartpageData()">Print techniques</button></p>
  <p>___</p>
  <p><input type="text" id="test" name="test" placeholder="test id" size="15"></p>
  <p><button onclick="getSpecificData()">Print Tech detail</button></p>
  <p><button onclick="getUsers()">Get users</button><p>
  <script>
    function getStartpageData(){
      var xhttp = new XMLHttpRequest();
      xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
          var response = JSON.parse(this.responseText);
          console.log(response);
        }
      };
      xhttp.open("GET", "app/api.php?action=startpage", true);
      xhttp.send();
    }
    function getSpecificData(){
      var xhttp = new XMLHttpRequest();
      xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
          var response = JSON.parse(this.responseText);
          console.log(response);
        }
      };
      var test = document.getElementById("test").value;
      xhttp.open("GET", "app/api.php?action=specific&id=" + encodeURIComponent(test), true);
      xhttp.send();
    }
    function getUsers(){
      var xhr = new XMLHttpRequest();
      xhr.open("GET", "app/api.php?action=targets", true);
      xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
          var response = JSON.parse(this.responseText);
          console.log(response);
        }
      }
      xhr.send();
    }
  </script>
</body>
</html>