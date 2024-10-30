function calcul(){

    let totalHSaisie = 0;
    let totalHNorm = 0;
    let totalH25 = 0;


    let listInputHSaisie = document.getElementsByClassName("HSaisie");

    for (let InputHSaisie of listInputHSaisie) {
        
            let hSaisie = parseFloat(InputHSaisie.value);
            if(isNaN(hSaisie)){
                hSaisie = 0;
            }
            totalHSaisie += hSaisie;
            jour = InputHSaisie.id.split('H')[0];


                document.getElementById(jour+"HS25").value = "";
                document.getElementById(jour+"HS50").value = "";
                document.getElementById(jour+"HCompl").value = "";

                //si le total fais plus de 35 h
                if(totalHSaisie > 35){


                    if(totalHSaisie <= 43){

                        if(totalHNorm < 35){
                            document.getElementById(jour+"HNorm").value = 35 - totalHNorm;
                            totalHNorm += (35 - totalHNorm);
                            document.getElementById(jour+"HS25").value = totalHSaisie - 35;
                            totalH25 += (totalHSaisie - 35);

                        }else{
                            document.getElementById(jour+"HS25").value = hSaisie;
                            totalH25 += hSaisie;
                        }
                        
                    }else{

                        if(totalH25 < 8){
                            if((8 - totalH25) > 0){
                                document.getElementById(jour+"HS25").value = (8 - totalH25);
                                totalH25 += (8 - totalH25);
                                document.getElementById(jour+"HS50").value = hSaisie - document.getElementById(jour+"HS25").value;


                            }else{
                                console.log(totalHNorm);
                                // document.getElementById(jour+"HS25").value = 8 - totalHNorm
                            }
                        }else{
                            document.getElementById(jour+"HS50").value = hSaisie;
                        }
        
                        if(totalHSaisie >= 49){

                            if(totalHSaisie >= 57){

                                if(document.getElementById(jour+"HCompl").value == ""){
                                    document.getElementById(jour+"HCompl").value = 0;
                                }
                                document.getElementById(jour+"HCompl").value = parseFloat(document.getElementById(jour+"HCompl").value) + parseFloat((56 - 48) * 0.25);
                                console.log(jour);
                                console.log(parseFloat((totalHSaisie - 57) * 0.5));
                                console.log(document.getElementById(jour+"HCompl").value);
                                document.getElementById(jour+"HCompl").value = parseFloat(document.getElementById(jour+"HCompl").value) + parseFloat((totalHSaisie - 57) * 0.5);

                            }else if(totalHSaisie < 57){
                            }
                        }

                    }


                // si total <=35 mettre les heures dans h normale
                }else{


                    document.getElementById(jour+"HNorm").value = parseFloat(hSaisie);
                    totalHNorm += hSaisie;

                    document.getElementById(jour+"HS25").value = "";
                }

                document.getElementById("dimancheHS50").value = document.getElementsByClassName("dimancheHSaisie")[0].value;

                document.getElementById("totalHsaisie").value = totalHSaisie;

    }

}
