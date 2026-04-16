<?php
class ExtensoDolar {

    private static $unidades = array('', 'UM', 'DOIS', 'TRÊS', 'QUATRO', 'CINCO', 'SEIS', 'SETE', 'OITO', 'NOVE');
    private static $dezenas = array('', 'DEZ', 'VINTE', 'TRINTA', 'QUARENTA', 'CINQUENTA', 'SESSENTA', 'SETENTA', 'OITENTA', 'NOVENTA');
    private static $teens = array('DEZ', 'ONZE', 'DOZE', 'TREZE', 'QUATORZE', 'QUINZE', 'DEZESSEIS', 'DEZESSETE', 'DEZOITO', 'DEZENOVE');
    private static $centenas = array('', 'CEM', 'DUZENTOS', 'TREZENTOS', 'QUATROCENTOS', 'QUINHENTOS', 'SEISCENTOS', 'SETECENTOS', 'OITOCENTOS', 'NOVECENTOS');

    public static function numeroPorExtenso($numero) {
        if (!is_numeric($numero)) {
            return 'Número inválido';
        }

        $numero = (float)$numero;

        if ($numero == 0) {
            return 'ZERO DOLARES';
        }

        $partes = explode('.', number_format($numero, 2, '.', ''));
        $extenso = self::parteInteiraPorExtenso($partes[0]);

        // Tratamento especial para o singular
        if ($partes[0] == '1') {
            $extenso .= ' DOLAR';
        } else {
            $extenso .= ' DOLARES';
        }

        if (isset($partes[1]) && (int)$partes[1] > 0) {
            $extenso .= ' E ' . self::parteDecimalPorExtenso($partes[1]);
        }

        return $extenso;
    }

    private static function parteInteiraPorExtenso($numero) {
        $numero = (int)$numero;
        $extenso = '';

        if ($numero >= 1000) {
            $milhares = (int)($numero / 1000);
            $numero = $numero % 1000;
            if ($milhares == 1) {
                $extenso .= 'MIL';
            } else {
                $extenso .= self::parteInteiraPorExtenso($milhares) . ' MIL';
            }
            if ($numero > 0) {
                $extenso .= ' E ';
            }
        }

        if ($numero >= 100) {
            $centena = (int)($numero / 100);
            $numero = $numero % 100;
            if ($centena == 1 && $numero > 0) {
                $extenso .= 'CENTO';
            } else {
                $extenso .= self::$centenas[$centena];
            }
            if ($numero > 0) {
                $extenso .= ' E ';
            }
        }

        if ($numero >= 20) {
            $dezena = (int)($numero / 10);
            $numero = $numero % 10;
            $extenso .= self::$dezenas[$dezena];
            if ($numero > 0) {
                $extenso .= ' E ';
            }
        } elseif ($numero >= 10) {
            $extenso .= self::$teens[$numero - 10];
            $numero = 0;
        }

        if ($numero > 0) {
            if (!empty($extenso) && substr($extenso, -3) !== ' E ') {
                $extenso .= ' E ';
            }
            $extenso .= self::$unidades[$numero];
        }

        return trim($extenso);
    }

    private static function parteDecimalPorExtenso($numero) {
        $extenso = self::parteInteiraPorExtenso((int)$numero);
        if ((int)$numero == 1) {
            return $extenso . ' CENTAVO';
        }
        return $extenso . ' CENTAVOS';
    }
}
?>
