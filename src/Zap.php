<?php namespace IntegracoesImoveis;

use \lslucas\Files;
use \lslucas\Helpers;
use Nathanmac\Utilities\Parser\Parser;
use App\Http\Controllers\Admin\RealEstateController;
use App\RealEstate;

use Illuminate\Http\Request;

class Zap {

    public $storage_id = null, $data = null, $integracao = 'Zap', $importPhotos = true, $estate_id = null;

    public function load($input)
    {
        $Upload = new Files\Upload();
        $this->storage_id = $Upload->start(\Request::file('file'), $input);
    }

    public function parse($storage_id=null)
    {
        if ( !is_null($storage_id) )
            $this->storage_id = $storage_id;

        if ( !$this->storage_id )
            throw new \InvalidArgumentException('Nenhum arquivo para se fazer parse');

        $Retrieve = new \lslucas\Files\Retrieve();
        $Parser = new Parser();

        $xml = $Retrieve->getContents($this->storage_id);
        $parsed = $Parser->xml($xml)['Imoveis'];

        $this->data = $this->format($parsed);

        return $this->data;
    }

    public function format($payload)
    {
        $Numbers = new Helpers\Numbers();

        $formated = [];

        foreach ( $payload as $imoveis ) {

            $i=0;

            foreach ( $imoveis as $item ) {

                if ( @$item['PrecoVenda'] ) {
                    $acao = 'venda';
                    $valor = $item['PrecoVenda'];

                } elseif ( @$item['PrecoLocacao'] ) {
                    $acao = 'venda';
                    $valor = $item['PrecoLocacao'];

                } elseif ( @$item['PrecoLocacaoTemporada'] ) {
                    $acao = 'temporada';
                    $valor = $item['PrecoLocacaoTemporada'];

                }

                $titulo = $item['TipoImovel'].' no '.$item['Bairro'].', '.$item['Cidade'];
                $valor = $Numbers::decimalize($valor);

                if ( !isset($acao) )
                    continue;

                $formated[$i]['estate_id'] = $this->estate_id;
                $formated[$i]['origem'] = $this->integracao;
                $formated[$i]['codigo_custom'] = $item['CodigoCliente'];
                $formated[$i]['codigo'] = $Numbers::random('realestate');
                $formated[$i]['titulo'] = $titulo;
                $formated[$i]['acao'] = $acao;
                $formated[$i]['imovel'] = $item['TipoImovel'];
                $formated[$i]['endereco'] = $item['Endereco'];
                $formated[$i]['cidade'] = $item['Cidade'];
                $formated[$i]['bairro'] = $item['Bairro'];
                $formated[$i]['cep'] = $item['CEP'];
                $formated[$i]['numero'] = $item['Numero'];
                $formated[$i]['valor'] = $valor;
                $formated[$i]['valor_condominio'] = $Numbers::decimalize($item['PrecoCondominio']);
                $formated[$i]['area_util_m2'] = $Numbers::decimalize($item['AreaUtil']);
                $formated[$i]['area_total_m2'] = $Numbers::decimalize($item['AreaTotal']);
                $formated[$i]['obs'] = $item['Observacao'];
                //$formated[$i]['TipoOferta'] = $item['TipoOferta'];
                $formated[$i]['qtd_pessoas_temporada'] = $item['QtdPessoasTemporada'];
                $formated[$i]['qtd_andares'] = $item['QtdAndar'];
                $formated[$i]['qtd_apto_por_andar'] = $item['QtdUnidadesAndar'];

                $caract = [];
                $caract['Quartos'] = $item['QtdDormitorios'];
                $caract['Vagas de Garagem'] = $item['QtdVagas'];
                $caract['Suítes'] = $item['QtdSuites'];
                $caract['Churrasqueira'] = $item['Churrasqueira'];
                $caract['Banheiro'] = $item['Churrasqueira'];
                $caract['Sala de TV'] = $item['QtdSalas'];
                $caract['Elevadores Social'] = $item['QtdElevador'];
                $caract['Piscina Externa'] = $item['Piscina'];
                $caract['Sauna'] = $item['Sauna'];

                $formated[$i]['caracteristicas'] = $caract;

                $fotos = [];
                foreach ( $item['Fotos'] as $_fotos ) {

                    if ( isset($_fotos['URLArquivo']) )
                        $fotos[] = $_fotos['URLArquivo'];

                    foreach ( $_fotos as $foto ) {
                        if ( isset($foto['URLArquivo']) )
                            $fotos[] = $foto['URLArquivo'];
                    }
                }

                $formated[$i]['fotos'] = $fotos;

                $i++;

            }
        }


        return $formated;
    }

    public function save()
    {
        if ( !$this->data || !count($this->data) )
            throw new \InvalidArgumentException('Data invalido!');

        $Upload = new \lslucas\Files\Upload();
        $sum = 0;

        foreach ( $this->data as $item ) {
            $estate_id = $this->store($item);

            if ( isset($item['fotos']) && $this->importPhotos ) {
                foreach ( $item['fotos'] as $foto ) {
                    $Upload->start($foto, ['area' => 'realestate']);
                }
            }

            $sum++;
        }

        return $sum;
    }

    protected function store($request)
    {
        $RealEstate = new RealEstateController();

        $self = new RealEstate();

        $RealEstate->insertUpdate($self, $request);

        return $self->id;
    }

    public function importPhotos($bool=true)
    {
        $this->importPhotos = $bool;

        return;
    }

    public function estate_id($id=null)
    {
        $this->estate_id = $id;

        return;
    }


}
