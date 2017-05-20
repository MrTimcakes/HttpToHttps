particlesJS.load('particles-js', 'particlesjs-config.json', function() {
  console.log('Particles Enabled')
});

window.onscroll = function(){
  var navbar = document.querySelector('.navbar')

  if (window.pageYOffset > 49){
    if (navbar.classList.contains('transparent')){
      navbar.classList.remove('transparent')
      navbar.classList.add('opaque')
    }
  }else{
    if (navbar.classList.contains('opaque')){
      navbar.classList.remove('opaque')
      navbar.classList.add('transparent')
    }
  }
};
